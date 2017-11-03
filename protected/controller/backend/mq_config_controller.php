<?php
class mq_config_controller extends general_controller
{
    public function action_index()
    {
        $this->compiler('setting/mq_config.html');
    }
    
    public function action_operate()
    {
        $conf_file = "install/resources/config_dms.php";
		$local_conf = array
		(
			'project_id' => request('project_id', ''),
            'queue_id' => request('queue_id', ''),
//			'username' => request('username', ''),
//            'password' => request('password', '')
		);
		
        $module_conf = array
        (
//            'iam_endpoint' => request('iam_endpoint', ''),
//            'dms_endpoint' => request('dms_endpoint', ''),
//            'dms_region' => request('dms_region', ''),
			'dms_endpoint' => "https://43.254.0.22/v1.0/",
            'dms_region' => "cn-north-1",
            'ak' => request('ak', ''),
            'sk' => request('sk', ''),
            'project_id' => request('project_id', ''),
            'queue_id' => request('queue_id', ''),
            'group_id' => request('group_id', ''),
//            'username' => request('username', ''),
//            'password' => request('password', '')
        );

        //改服务配置
        if ($this->module_config($conf_file, $module_conf))
        {
            //改本地配置
            if ($this->local_config($conf_file, $local_conf))
            {
                $this->prompt('success', '修改配置成功', url($this->MOD.'/mq_config', 'index'));
            }
        }

        $this->prompt('error', '修改配置失败');

    }

    public function module_config($conf_file, $conf)
    {
        return $this->http_op($conf, $conf_file);
    }

    public function local_config($conf_file, $conf)
    {
        return $this->update_configs($conf_file, $conf);
    }

    public function http_op($data, $conf_file)
    {
        $http_url = $this -> get_config($conf_file, "config_url");
        $header = array
        (
            "Content-Type:application/json"
        );
        $post_data = array
        (
            'data' => $data
        );
        $content = json_encode($post_data);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $http_url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
    }

    function get_config($file, $ini, $type="string")
    {
        if(!file_exists($file)) return false;
        $str = file_get_contents($file);
        if ($type=="int"){
            $config = preg_match("/".preg_quote($ini)."=(.*);/", $str, $res);
            return $res[1];
        }
        else{
            $config = preg_match("/".preg_quote($ini)."=\"(.*)\";/", $str, $res);
            if($res[1]==null){
                $config = preg_match("/".preg_quote($ini)."='(.*)';/", $str, $res);
            }
            return $res[1];
        }
    }

    function update_config($file, $ini, $value, $type="string"){
        if(!file_exists($file))
        {
            return false;
        }
        $str = file_get_contents($file);
        $str2="";
        if($type=="int"){
            $str2 = preg_replace("/".preg_quote($ini)."=(.*);/", $ini."=".$value.";",$str);
        }
        else{
            $str2 = preg_replace("/".preg_quote($ini)."=(.*);/",$ini."=\"".$value."\";",$str);
        }
        file_put_contents($file, $str2);
        return true;
    }

    function update_configs($file, $conf, $type="string"){
        if(!file_exists($file))
        {
            return false;
        }
        $str = file_get_contents($file);
        $str2= $str;
        foreach ($conf as $key => $value)
        {
            if($type=="int"){
                $str2 = preg_replace("/".preg_quote($key)."=(.*);/", $key."=".$value.";",$str2);
            }
            else{
                $str2 = preg_replace("/".preg_quote($key)."=(.*);/",$key."=\"".$value."\";",$str2);
            }
        }
        file_put_contents($file, $str2);
        return true;
    }
}