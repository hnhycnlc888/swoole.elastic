<?php

require_once __DIR__ . "/DataImporter.php";
/**
 *
 * @author 梁伟明 <LiangWeiMing 61843912@qq.com>
 */
class ElasticUpdater {
	
	private $serv;
	
	//服务器是否已经在执行任务中
	private $running = 0;
	
	private $taskList = null;
	
	private $config	  = null;
	
	private $log_file = '';
	
	private $log_ext = '.txt';
	
	public function __construct($config = array()) {	
		$this->taskList			= $config['TASK_LIST'];
		$this->config			= $config;
		
		//检查server是否已经开启了，如果已经开启了，则直接退出
		if(is_file($this->config['LOCK_NAME'])){
			die($this->_getTime() . "ElasticUpdater is running \n");
		}
								
		$this->serv = new swoole_server('0.0.0.0', 9501);
		//生成log_file文件名
		$this->_checkLogFile($this->serv);
		$this->serv->set(array(
			//守护进程运行方式
			'daemonize'			=> $config['DAEMONIZE'],
			'worker_num'		=> $config['WORKER_NUM'],			
			'task_worker_num'	=> $config['TASK_WORKER_NUM'],
			'log_file'			=> $this->log_file
		));
		
		$this->serv->on('start', array($this, 'onStart'));
		$this->serv->on('connect', array($this, 'onConnect'));
		$this->serv->on('receive', array($this, 'onReceive'));
		$this->serv->on('close', array($this, 'onClose'));
		
		$this->serv->on('task', array($this, 'onTask'));
		$this->serv->on('finish', array($this, 'onFinish'));
		
		$this->serv->start();
	}
	
	/**
	 * 服务器开始时执行的操作
	 * @param swoole_server $serv
	 */
	public function onStart($serv){
		//生成server.lock文件
		$serverFile = fopen($this->config['LOCK_NAME'], "w") or die("Unable to open server.lock!");
		fclose($serverFile);
		echo $this->_getTime() . "Start\n";
	}
	
	/**
	 * 有客户端连接时的操作
	 * @param swoole_server $serv
	 * @param int $fd	客户端连接的文件描述符
	 * @param int $from_id
	 */
	public function onConnect($serv, $fd, $from_id){
		$serv->send($fd, $this->_getTime() . "Hello {$fd}!\n");
	}
	
	/**
	 * 收到客户端发送的消息时的操作
	 * @param swoole_server $serv
	 * @param int $fd 客户端连接的文件描述符
	 * @param int $from_id
	 * @param mixed $data
	 */
	public function onReceive(swoole_server $serv, $fd, $from_id, $data){
		if(!$data){
			die($this->_getTime() . "data is empty!");
		}
		//开始执行任务
		if($data == 'start' && $this->running == 0){
			$this->running = 1;	
			$this->assignTask($serv, $fd);
		} else if($data == 'stop'){
			//关闭服务器
			if(is_file($this->config['LOCK_NAME'])){
				@unlink($this->config['LOCK_NAME']);
			}
			$this->serv->shutdown();
		}
	}
	
	/**
	 * 任务进行时的操作
	 * @param swoole_server $serv
	 * @param int $task_id
	 * @param int $from_id
	 * @param mixed $data
	 */
	public function onTask(swoole_server $serv, $task_id, $from_id, $data){
		//日志检测任务
		if(is_string($data) && $data == 'log_task'){
			$this->_checkLogFile($serv);
			$serv->finish('');
			return;
		}
		if($this->config['DEBUG'] === true){
			echo "====================================================================\n";
			echo $this->_getTime() . "{$data['taskName']}任务开始\n";
		}
		$now = time();
		$start_time = microtime(true);
		//任务开始时间
		$data['start_time'] = $start_time;
		$importer = new DataImporter($data, $this->config['DB'], $this->config['ELASTIC_SEARCH']);
		//获取上次执行时间
		$filename = __DIR__ . "/counter/" . $data['taskName'];
		if(is_file($filename)){
			$last_time = file_get_contents($filename);
		} else {
			$last_time = $now;
		}
		
		//有10秒（1个任务执行间隔）的时间差，
		//因为上一次做索引的时候可能数据库正在写入数据，导致elastic获取的数据不全
		$last_time -= 10;
		$sql = $data['sql'];
		//参数替换
		if(isset($data['parameter']) && !empty($data['parameter'])){
			$args = array();
			$args[] = $sql;
			foreach ($data['parameter'] as $param_name) {
				if($param_name == 'last_index_time'){
					$args[] = $last_time;
				}
			}
			$sql = call_user_func_array('sprintf', $args);	
		}
				
		//index，create，update等更新elastic索引操作
		if($data['act'] != 'delete'){
			$total = $importer->getTotal($sql);
			$count = ceil($total / $data['per_size']);	
			if($count){		
				for($i = 0; $i < $count; $i++){
					$offset = $i * $data['per_size'];
					$sql .= " LIMIT $offset, {$data['per_size']}";
					$rows = $importer->select($sql);
					if($rows) {
						$response = $importer->import($rows, $data['act'], $data['id_field'], $data['elastic_type']);
						if($response['errors'] == false){	
							$data['result'] = '成功';
						} else {
							$data['result'] = '失败';
						}
					}
				}
			} else {
				$data['result'] = '没有数据需要更新';
			}
		//删除elastic索引操作
		} else {
			$ids = $importer->select($sql, DataImporter::one_array);
			if($ids){
				$response = $importer->import($ids, $data['act'], $data['id_field'], $data['elastic_type']);
				if($response['errors'] == false){
					//删除数据库中的记录
					$del_res = $importer->delete($data['after_sql']);
					if($del_res){
						$data['result'] = '成功';
					} else {
						$data['result'] = '失败';
					}
				} else {
					$data['result'] = '失败';
				}
			} else {
				$data['result'] = '没有数据需要更新';
			}
		}
		//关闭数据库连接
		$importer->close();
		
		file_put_contents($filename, $now);
		
		$serv->finish($data);
	}
	
	/**
	 * 任务完成时的操作
	 * @param swoole_server $serv
	 * @param int $task_id
	 * @param mixed $data
	 */
	public function onFinish(swoole_server $serv, $task_id, $data){
		if($data && $this->config['DEBUG'] === true) {
			$end_time = microtime(true);
			//任务用时
			$use_time = number_format(($end_time - $data['start_time']), 3);
			echo "====================================================================\n";
			echo $this->_getTime() . "{$data['taskName']}任务完成，结果为：{$data['result']}, 用时：{$use_time}s\n";
		}
	}
	
	/**
	 * 客户端断开连接时的操作
	 * @param swoole_server $serv
	 * @param int $fd 客户端连接的文件描述符
	 * @param int $from_id
	 */
	public function onClose($serv, $fd, $from_id){
		echo $this->_getTime() . "Client {$fd} close connection\n";
	}
	
	/**
	 * 根据任务列表分配任务
	 * @param swoole_server $serv
	 * @param int $fd 客户端连接的文件描述符
	 */
	protected function assignTask(swoole_server $serv, $fd){
		if(!empty($this->config['TASK_LIST'])){			
			//检测运行日志的任务，将日志文件按日划分（1小时运行一次）
			$this->serv->tick(3600*1000, function(){
				$this->serv->task('log_task');
			});
			$taskWorkerNum = 0;
			foreach($this->config['TASK_LIST'] as $taskName => $taskData){
				$data = array(
					'taskName'		=> $taskName,
					'taskWorkerNum'		=> $taskWorkerNum
				);
				$taskData = array_merge($taskData, $data);
				$this->serv->tick($taskData['time'], function() use ($serv, $fd, $taskData){
					$this->serv->task($taskData, $taskData['taskWorkerNum']);
				});
				$taskWorkerNum ++;
			}			
		} else {
			echo "task list is empty, Server will stop\n";
			$this->serv->shutdown();
		}
	}
	
	/**
	 * 把日志文件按日存放
	 * @param swoole_server $serv
	 */
	private function _checkLogFile(swoole_server $serv){
		$now_file = $this->_getLogFile();
		$dir = dirname($now_file);
		if(!is_dir($dir)){
			if(!mkdir($dir, 0777, true)){
				die("创建日志目录失败！");
			}
		}
		if(!is_file($now_file)){
			echo $this->_getTime() . "**********日志文件：$now_file **********\n";
			$this->log_file = $now_file;
//			$serv->set(array(
//				'log_file' => $this->log_file
//			));
			$serv->setting['log_file'] = $this->log_file;
		}
		unset($now_file);
	}

	private function _getTime(){
		return date('Y-m-d H:i:s -- ');
	}
	
	private function _getLogFile(){
		return $this->config['LOG_DIR'] . '/' . date('Y-m') . '/' . date('d') . $this->log_ext;
	}
}
$config = include('config.php');

if(!$config['LOCK_NAME']){
	die('LOCK_NAME is not set');
} 
$server = new ElasticUpdater($config);
