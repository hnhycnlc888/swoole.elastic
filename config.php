<?php

return array(
	//是否以守护进程的方式运行
	'DAEMONIZE'			=> true,
	//是否开启日志功???	
	'DEBUG'				=> true,
	//设置启动的worker进程数量，PHP代码中是全异步非阻塞，worker_num配置为CPU核数???-4倍即???	
	'WORKER_NUM'		=> 8,
	//任务工作进程???	
	'TASK_WORKER_NUM'	=> 20,
	//服务器运行时生成的锁文件
	'LOCK_NAME'			=> __DIR__ . '/server.lock',
	//日志文件
	'LOG_DIR'			=> __DIR__ . '/logs',
	//数据库配???	
		'DB'			=> array(
		'host'			=> 'rm-bp1u4eh55h40q7y8e35910.mysql.rds.aliyuncs.com',
		'port'			=> 3306,
		'db'			=> 'zhihuo',
		'username'		=> 'root',
		'password'		=> 'Telpo@syhuo2019!@#'
	),
	
	'ELASTIC_SEARCH'	=> array(
		'hosts'			=> array(
			'http://admin:RYM1zmXmPPnFtKWG@127.0.0.1:9200'
		),
		'index'			=> 'syhuo'
	),
	//任务列表
	'TASK_LIST'				=> array(
		'view_devices'          => array(
			//执行任务间隔时间
			'time'                  => 10000,
			//数据库名???                        
			'db'                    => 'zhihuo',
			//数据表名???                        
			'table'                 => 'zh_view_devices',
			//获取数据的sql语句
			'sql'                   => 'SELECT * FROM zh_view_devices WHERE update_time > %d',
			//sql语句参数
			'parameter'             => array('last_index_time'),
			//elastic索引的执行类型（index，create，update，delete???                        
			'act'                   => 'index',
			//主键字段名称
			'id_field'              => 'id',
			//每次获取数据???                        
			'per_size'              => 1000,
			//elastic索引类型
			'elastic_type'  => 'view_devices'
        ),
		'view_devices_delete'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_delete_trigger',
			'sql'			=> 'SELECT * FROM zh_view_delete_trigger WHERE type = "device"',
			//获取数据并且索引删除成功后，对数据库的操???			
			'after_sql'		=> 'DELETE FROM zh_view_delete_trigger WHERE type = "device"',
			'act'			=> 'delete',
			'id_field'		=> 'id',
			'elastic_type'	=> 'view_devices'
		),
		'view_member'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_member',
			'sql'			=> 'SELECT * FROM zh_view_member WHERE update_time > %d',
			'parameter'		=> array('last_index_time'),
			'act'			=> 'index',
			'id_field'		=> 'id',
			'per_size'		=> 1000,
			'elastic_type'	=> 'view_member'
		),
		'view_member_delete'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_delete_trigger',
			'sql'			=> 'SELECT * FROM zh_view_delete_trigger WHERE type = "member"',
			'after_sql'		=> 'DELETE FROM zh_view_delete_trigger WHERE type = "member"',
			'act'			=> 'delete',
			'id_field'		=> 'id',
			'elastic_type'	=> 'view_member'
		),
		'view_merchant'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_merchant',
			'sql'			=> 'SELECT * FROM zh_view_merchant WHERE update_time > %d',
			'parameter'		=> array('last_index_time'),
			'act'			=> 'index',
			'id_field'		=> 'id',
			'per_size'		=> 1000,
			'elastic_type'	=> 'view_merchant'
		),
		'view_merchant_delete'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_delete_trigger',
			'sql'			=> 'SELECT * FROM zh_view_delete_trigger WHERE type = "merchant"',
			'after_sql'		=> 'DELETE FROM zh_view_delete_trigger WHERE type = "merchant"',
			'act'			=> 'delete',
			'id_field'		=> 'id',
			'elastic_type'	=> 'view_merchant'
		),
		'view_login_history'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_login_history',
			'sql'			=> 'SELECT * FROM zh_view_login_history WHERE login_time > %d',
			'parameter'		=> array('last_index_time'),
			'act'			=> 'index',
			'id_field'		=> 'id',
			'per_size'		=> 1000,
			'elastic_type'	=> 'view_login_history'
		),
		'view_login_history_delete'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_delete_trigger',
			'sql'			=> 'SELECT * FROM zh_view_delete_trigger WHERE type = "login_history"',
			'after_sql'		=> 'DELETE FROM zh_view_delete_trigger WHERE type = "login_history"',
			'act'			=> 'delete',
			'id_field'		=> 'id',
			'elastic_type'	=> 'view_login_history'
		),
		'view_app_downloads'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_app_downloads',
			'sql'			=> 'SELECT * FROM zh_view_app_downloads WHERE down_time > %d',
			'parameter'		=> array('last_index_time'),
			'act'			=> 'index',
			'id_field'		=> 'id',
			'per_size'		=> 1000,
			'elastic_type'	=> 'view_app_downloads'
		),
		'view_app_downloads_delete'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_delete_trigger',
			'sql'			=> 'SELECT * FROM zh_view_delete_trigger WHERE type = "app_download"',
			'after_sql'		=> 'DELETE FROM zh_view_delete_trigger WHERE type = "app_download"',
			'act'			=> 'delete',
			'id_field'		=> 'id',
			'elastic_type'	=> 'view_app_downloads'
		),
		'view_app_releases'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_app_releases',
			'sql'			=> 'SELECT * FROM zh_view_app_releases WHERE update_time > %d',
			'parameter'		=> array('last_index_time'),
			'act'			=> 'index',
			'id_field'		=> 'id',
			'per_size'		=> 1000,
			'elastic_type'	=> 'view_app_releases'
		),
		'view_app_releases_delete'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_delete_trigger',
			'sql'			=> 'SELECT * FROM zh_view_delete_trigger WHERE type = "app_release"',
			'after_sql'		=> 'DELETE FROM zh_view_delete_trigger WHERE type = "app_release"',
			'act'			=> 'delete',
			'id_field'		=> 'id',
			'elastic_type'	=> 'view_app_releases'
		),
		'view_pay_history'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_pay_history',
			'sql'			=> 'SELECT * FROM zh_view_pay_history WHERE update_time_pay > %d OR update_time_dev > %d',
			'parameter'		=> array('last_index_time', 'last_index_time'),
			'act'			=> 'index',
			'id_field'		=> 'id',
			'per_size'		=> 1000,
			'elastic_type'	=> 'view_pay_history'
		),
		'view_pay_history_delete'		=> array(
			'time'			=> 10000,
			'db'			=> 'zhihuo',
			'table'			=> 'zh_view_delete_trigger',
			'sql'			=> 'SELECT * FROM zh_view_delete_trigger WHERE type = "pay_history"',
			'after_sql'		=> 'DELETE FROM zh_view_delete_trigger WHERE type = "pay_history"',
			'act'			=> 'delete',
			'id_field'		=> 'id',
			'elastic_type'	=> 'view_pay_history'
		)
	)
);
