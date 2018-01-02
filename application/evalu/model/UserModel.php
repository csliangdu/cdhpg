<?php
namespace app\evalu\model;

use think\Model;
use think\Loader;
use app\evalu\logic\LoginLogic;


class UserModel extends Model
{
	protected $pk = 'user_id';
	protected $table = 'user';
	protected $autoWriteTimestamp = true;		//自动转化时间戳
	// 定义时间戳字段名
	protected $createTime = 'register_date';
	protected $updateTime = 'last_login';
	protected $resultSetType = 'collection';
	
	public function getStatusAttr($value)
	{
	    $status = [0=>'禁用',1=>'正常'];
	    return $status[$value];
	}
	
	public function getPassAttr($value)
	{
	    return md5($value);
	}
	
	//与角色表多对多关联,不成功
// 	public function group(){
// 	    return $this->belongsToMany('GroupModel','group_access','uid','user_id');
// 	}
	
	/*
	 * 登录
	 */
	public function login($data)
	{
		//		1.验证
		$validate = Loader::validate('UserValidate');
		// 		如果验证不通过
		if(!$validate->check($data)){
			return ['valid'=>0,'msg'=>$validate->getError()];
		}
		// 		2.比对用户名和密码
// 		halt($data);
		$userInfo = $this->where('user_name',$data['user_name'])->where('pass',$data['pass'])->find();
		if(!$userInfo)
		{
			//说明在数据库没找到用户
			return ['valid'=>0,'msg'=>'用户名或者密码不正确']; 
		}
		// 		3.写入session，更新数据库的最后登录时间，最后登录ip，总登录次数
		session('user.user_id',$userInfo['user_id']);
		session('user.user_name',$userInfo['user_name']);
		$ip = LoginLogic::getIP();		
		LoginRecordsModel::create([
				'user_name'	=>	$data['user_name'],
				'login_ip'	=>	$ip,
		]);
		$this->save([
				'login_times'  => $userInfo['login_times']+1,
				'last_ip' => $ip,
		],['user_id' => $userInfo['user_id']]);

		return ['valid'=>1,'msg'=>'登录成功'];
	}
	
	//注册
	public function signup($data)
	{
// 	    halt($data);
		$validate = Loader::validate('UserSignupValidate');
		// 		如果验证不通过
		if(!$validate->check($data)){
			return ['valid'=>0,'msg'=>$validate->getError()];
		}
		
		try{
		    
			$res = $this->data($data)->allowField(true)->save();
// 			halt($data);
		}catch(\Exception $e){
			//	插入失败，错误代码是10501时，表示用户名重复
			if($e->getCode()==10501)
			{
				return ['valid'=>0,'msg'=>'用户名已被使用,再想一个吧'];;
			}else{
				dump($e);
			}
		}
// 			直接执行登录了
		session('user.user_id',$this->user_id);
		session('user.user_name',$data['user_name']);
		$ip = LoginLogic::getIP();
		LoginRecordsModel::create([
		    'user_name'	=>	$data['user_name'],
		    'login_ip'	=>	$ip,
		]);
		$this->save([
		    'login_times'  => 1,
		    'last_ip' => $ip,
		],['user_id' => $this->user_id]);
		//新注册用户默认权限是普通会员
		$group=array(
		    'uid'=>$this->user_id,
		    'group_id'=>4
		);
		(new GroupAccessModel()) ->insert($group);
		return ['valid'=>1,'msg'=>'注册成功，请登录'];
			
	}
	
	//使用后台添加用户
	public function add_user($data){
	    try{
	        $res = $this->data($data)->allowField(true)->save();
	    }catch(\Exception $e){
	        //	插入失败，错误代码是10501时，表示用户名重复
	        if($e->getCode()==10501)
	        {
	            return ['valid'=>0,'msg'=>'用户名已被使用,再想一个吧'];
	        }else{
	            dump($e);
	            return ['valid'=>0,'msg'=>$this->getError()];
	        }
	    }
	    // 			记录ip
	    $ip = LoginLogic::getIP();
	    $this->save([
	        'login_times'  => 0,
	        'last_ip' => $ip,
	    ],['user_id' => $this->user_id]);
	    //把权限记录上去
	    $GA = new GroupAccessModel();
	    foreach ($data['group_ids'] as $k => $v) {
	        $group=array(
	            'uid'      =>  $this->user_id,
	            'group_id' =>  $v
	        );
	        $GA ->insert($group);
	    }
	    return ['valid'=>1,'msg'=>'添加成功'];
	    
	    
	}
	
}
