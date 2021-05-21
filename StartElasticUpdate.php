<?php

/**
 *
 * @author 梁伟明 <LiangWeiMing 61843912@qq.com>
 */
class StartElasticUpdate {
	
	private $cli;
	
	public function __construct() {
		$this->cli = new swoole_client(SWOOLE_SOCK_TCP);
		//NOTICE	swFactoryProcess_finish (ERROR 1004): send 32 byte failed, because connection[fd=1] is closed
		//$this->cli = new swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
	}
	
	public function connect(){
		$fp = $this->cli->connect('127.0.0.1', 9501, 1);
		if(!$fp){
			echo "Error:{$fp->errMsg}[{$fp->errCode}]\n";
		}
		
		$this->cli->send('start');
	}
}

$client = new StartElasticUpdate();
$client->connect();
