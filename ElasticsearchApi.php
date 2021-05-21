<?php

use Elasticsearch\ClientBuilder;
require_once __DIR__ . "/vendor/autoload.php";

/**
 * elasticsearch 接口类
 * @author 梁伟明 <LiangWeiMing 61843912@qq.com>
 */
class ElasticsearchApi {
	
	public $client = null;
	//配置选项
	public $config = array();
	//每次搜索最多获取的结果数（0的时候只获取结果总数，不返回搜索结果）
	public $size = 1000;
	
	public function __construct($hosts = array('http://localhost:9200/'), $index = 'syhuo', $type = '', $size = 1000) {
		$this->config['index']  = $index;
		$this->config['type']	= $type;
		$this->config['hosts']	= $hosts;
		$this->size = $size;
		$this->client = ClientBuilder::create()->setHosts($this->config['hosts'])->build();
	}
	
	private function _handleParams($params = array(), $index = '', $type = ''){
		if($index){
			$params['index'] = $index;
		}
		if($type){
			$params['type'] = $type;
		}
		
		if(!isset($params['type']) || empty($params['type'])){
			if(!$this->config['type']){
				throw new \Exception('必须指定索引类型[type]');
			} else {
				$params['type'] = $this->config['type'];
			}			
		}
		if(!isset($params['index']) || empty($params['index'])){
			$params['index'] = $this->config['index'];
		}
		return $params;
	}
	
	private function _exception(\Exception $e){
		$code = $e->getCode();
		$message = $e->getMessage();
		if($code == 404){
			$message = json_decode($message, true);
		}
		$result					= array();
		$result['code']			= $code;
		$result['message']		= $message;
		return $result;
	}
	
	/**
	 * 根据文档id获取一个文档
	 * @param array $params
	 * @return array
	 */
	public function get($id, $type = ''){
		$params = $this->_handleParams(array(), $this->config['index'], $type);
		$params['id'] = $id;
		try {
			$response = $this->client->get($params);
			if($response['found']){
				return $response['_source'];
			} else {
				return array();
			}
		} catch (\Exception $e) {
			return $this->_exception($e);
		}		
	}
	
	/**
	 * 添加一条文档记录
	 * @param array $params
	 * @throws \Exception
	 */
	public function add($data, $type = '', $id_field = 'id'){
		$params = $this->_handleParams(array(), $this->config['index'], $type);
		try {
			if($data){
				if(isset($data[$id_field])){
					$params['id'] = $data[$id_field];
				}
				$params['body']	= $data;
				$response = $this->client->index($params);
				if(isset($response['_id'])){
					return $response['_id'];
				} else {
					return false;
				}
			} else {
				throw new \Exception('数据为空');
			}
		} catch(\Exception $e) {
			return $this->_exception($e);
		}
	}
	
	/**
	 * 更新一个文档索引
	 * @param int $id 文档id
	 * @param array $data 更新数据
	 * @param string $type 索引类型
	 * @return string 错误消息
	 */
	public function update($id, $data, $type = ''){
		$params = $this->_handleParams(array(), $this->config['index'], $type);
		try {
			$params['id'] = $id;
			if($data){
				$params['body']['doc'] = $data;
				$response = $this->client->update($params);
				if($response){
					return true;
				}
			}
			return false;
		} catch (\Exception $e) {
			return $this->_exception($e);
		}
	}
	
	/**
	 * 删除一条文档记录
	 * @param int $id 文档id
	 * @param string $type 索引类型
	 * @return boolean
	 */
	public function delete($id, $type = ''){
		$params = $this->_handleParams(array(), $this->config['index'], $type);
		$params['id'] = $id;
		
		try {
			$response = $this->client->delete($params);
			if($response){
				return true;
			}
			return false;
		} catch (\Exception $e) {
			return $this->_exception($e);
		}
	}
	
	/**
	 * 搜索索引
	 * @param array $where 搜索条件
	 * @param string $type 索引类型	 
	 * @param bool $return_source 是否返回源数据
	 * @param string $strategy 搜索策略
	 * @return mixed
	 */
	public function search($where = array(), $type = '', $return_source = true, $strategy = 'normal'){
		$params = $this->_handleParams(array(), $this->config['index'], $type);
					
		if(!$return_source){
			$params['_source'] = false;
		}
		//起始文档数，相当于mysql的offset
		if(isset($where['_from'])){
			$params['from'] = $where['_from'];
			unset($where['_from']);
		}
		//结束文档数，相当于mysql的limit
		if(isset($where['_size'])){
			$params['size'] = $where['_size'];
			unset($where['_size']);
		} else {
			$params['size'] = $this->size;
		}		
		
		//根据不同的搜索策略和where条件生成elasticsearch查询数组
		switch ($strategy) {
			case 'normal' :
				$body = $this->_normal_search($where);
				break;
		}
		
		$params['body'] = $body;
		try {
			$response = $this->client->search($params);
			if($response){
				$response['search']		= $params;
				$response['took']		= $response['took'];
				$response['total']		= $response['hits']['total'];
				$response['hits']		= $response['hits']['hits'];
				return $response;
			}
			return false;
		} catch (\Exception $e) {
			return $this->_exception($e);
		}
	}
	
	/**
	 * 普通搜索策略
	 * @param array $where 搜索条件
	 * @return mixed
	 */
	private function _normal_search($where){
		$body = $query = $filter = $bool = $must = array();
		//文档排序，相当于mysql的order
		if(isset($where['_sort'])){
			$body['sort'] = $this->_get_sort_array($where['_sort']);
			unset($where['_sort']);
		}
		foreach($where as $field => $condition){
			if(!is_array($condition)){
				//相当于mysql中的'='查询
				//$filter[] = $this->_get_term_array($field, $condition);
				$must[] = $this->_get_term_array($field, $condition);
			} else if(is_array($condition)){
				$symbol = strtolower($condition[0]);
				$value = $condition[1];
				if(isset($condition[2])){
					$value2 = $condition[2];
				}
				//相当于相当于mysql中的'>', '>=', '<', '<='查询
				if($symbol == 'gt' || $symbol == 'gte' || $symbol == 'lt' || $symbol == 'lte'){
					$must[]['range'][$field] = array($symbol => $value);
				}
				if($symbol == '><' || $symbol == '<>'){
					$must[]['range'][$field] = array('gt' => $value, 'lt' => $value2);
				}
				if($symbol == '>=<' || $symbol == '<=>'){
					$must[]['range'][$field] = array('gte' => $value, 'lte' => $value2);
				}
				
				if($symbol == 'like'){
					$must[]['match'][$field] = $value;
				}
				if($symbol == 'wildcard'){
					$must[]['wildcard'][$field] = $value;
				}
			}
		}
		if(!empty($must)){
			$bool['must']		= $must;
		}
		if(!empty($filter)){
			$bool['filter']		= $filter;
		}
		if(!empty($bool)){
			$query['bool']		= $bool;
		}
		if(!empty($query)){
			$body['query']		= $query;
		}		
		return $body;
	}
	
	/**
	 * 根据键值生成term级别的查询数组
	 * @param string $field
	 * @param mixed $value
	 * @return array
	 */
	private function _get_term_array($field, $value){
		$term_array = array();
		$term_array['term'][$field] = $value;
		return $term_array;
	}
	
	/**
	 * 根据键值生成match级别的查询数组
	 * @param string $field
	 * @param mixed $value
	 * @return array
	 */
	private function _get_match_array($field, $value){
		$match_array = array();
		$match_array['match'][$field] = $value;
		return $match_array;
	}
	
	/**
	 * 根据_sort字段生成sort查询参数
	 * @param array $sort
	 * @return array
	 */
	private function _get_sort_array($sort = array()){
		$sort_array = array();
		foreach ($sort as $field => $value) {
			$sort_array[$field] = array(
				'order'	=> $value
			);
		}
		return $sort_array;
	}
	
	/**
	 * 从搜索结果中获取_source字段的结果集
	 * @param array $search_data
	 * @return array
	 */
	public function get_source_from_result($search_data = array()){
		$result = array();
		foreach ($search_data as $data) {
			$id = $data['_id'];
			$result[$id] = $data['_source'];			
 		}
		return $result;
	}
	
	/**
	 * 批量更新索引
	 * @param array $data 要更新的数据
	 * @param string $bulk_type 更新类型
	 * @param string $id_field 文档id字段
	 * @param string $type 文档类型
	 * @return mixed
	 */
	public function bulk($data, $bulk_type = 'add', $id_field = 'id', $type = ''){
		$bulk_type = strtolower($bulk_type);
		if($bulk_type == 'add'){
			$bulk_type = 'index';
		}
		$bulk_data = $this->_get_bulk_data($data, $bulk_type, $id_field, $type);
		try {
			$params = array(
				'body' => $bulk_data
			);
			$response = $this->client->bulk($params);
			return $response;
		} catch (\Exception $e) {
			$this->_exception($e);
		}
	}
	
	/**
	 * 处理bulk数据
	 * @param array $bulk_data 要处理的数据
	 * @param string $bulk_type 更新类型
	 * @param string $id_field 文档id字段
	 * @param string $type 文档类型
	 * @return mixed
	 * @throws \Exception
	 */
	private function _get_bulk_data($bulk_data, $bulk_type, $id_field, $type){
		$bulk_array = array('index', 'create', 'delete', 'update');
		$return = array();
		$params = array(
			'index'		=> $this->config['index'],
			'type'		=> $type
		);
		$params = $this->_handleParams($params);
		if(in_array($bulk_type, $bulk_array)){
			foreach($bulk_data as $data){
				$meta = array(
					'_index'		=> $params['index'],
					'_type'			=> $params['type'],
				);
				if($id_field && $bulk_type != 'delete'){
					$meta['_id']	= $data[$id_field];
				} else {
					$meta['_id']	= $data;
				}
				
				$return[][$bulk_type] = $meta;
				if($bulk_type == 'delete'){
					continue;
				} else {
					if($bulk_type == 'update'){
						$return[]['doc'] = $data;
					} else {
						$return[] = $data;
					}
				}			
			}
			return $return;
		} else {
			throw new \Exception("bulk类型[$bulk_type]不存在", 0);
		}
	}
	
	public function update_by_query($type, $where, $data, $update_type = 'script'){
		$search_array = $this->_normal_search($where);
		$url = $this->config['hosts'][0] . '/' . $this->config['index'] . "/$type/_update_by_query";
		if(stripos($this->config['hosts'][0], 'http://') === false){
			$url = 'http://' . $url;
		}
		$data_array = array($update_type => $data);
		$content = array_merge($data_array, $search_array);
		$options = array(
			'http'	=> array(
				'method'	=> 'POST',
				'header'	=> 'Content-type:application/x-www-form-urlencoded',
				'content'	=> json_encode($content)
			)
		);
		$context = stream_context_create($options);
		$response = file_get_contents($url, false, $context);
		return json_decode($response, true);
	}
}