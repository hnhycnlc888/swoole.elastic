<?php

/**
 * elasticsearch数据导入
 */

require_once __DIR__ . "/ElasticsearchApi.php";

class DataImporter {
	private $db				= '';
	
	public $connect			= null;
	
	public $elastic			= null;
	
	const one_array			= 1;
	const two_array			= 2;
	
	public function __construct($taskConfig = array(), $dbConfig = array(), $elasticConfig = array()) {
		if(isset($taskConfig['db']) && $taskConfig['db']){
			$this->db	 = $taskConfig['db'];
		} else {
			$this->db    = $dbConfig['db'];
		}
			
		//初始化数据库连接
		$dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$this->db}";
		$this->connect = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
		$this->connect->exec('set names utf8');
		//初始化elasticsearch连接	
		$this->elastic = new ElasticsearchApi($elasticConfig['hosts'], $elasticConfig['index'], $taskConfig['elastic_type']);
	}
	
	public function select($sql, $array_type = self::two_array){
		$res = $this->connect->query($sql);
		if(!$res){
			return $this->connect->errorInfo();
		}
		$return = array();
		while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
			if($array_type == self::two_array){
				$return[] = $row;
			} else if($array_type == self::one_array){
				$return[] = $row['id'];
			}
		}	
		return $return;
	}
	
	public function delete($sql){
		$res = $this->connect->query($sql);
		return $res;
	}
	
	public function getTotal($sql){
		$res = $this->connect->query($sql);
		return $res->rowCount();
	}
	
	public function import($data, $bulk_type = 'add', $id_field = 'id', $type = ''){
		return $this->elastic->bulk($data, $bulk_type, $id_field, $type);
	}
	
	public function close(){
		$this->connect = null;
	}
}