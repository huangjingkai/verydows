<?php
class simulate_controller extends general_controller
{
    public function action_index()
    {
        $this->compiler('tools/service_switch.html');
    }
    
    public function action_start()
    {
        $conf_file = "install/resources/config_dms.php";
        $simulate_url = $this->get_config($conf_file, "simulate_url");
        $header = array
        (
            "Content-Type:application/json"
        );
        $post_data = array
        (
            'action' => "stop"
        );
        $content = json_encode($post_data);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $simulate_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

        $res = curl_exec($curl);
        curl_close($curl);
		if($res == true)
		{
			$result = array('status' => 'success');
			echo json_encode($result);
		}
    }

    public function action_stop()
    {
        $conf_file = "install/resources/config_dms.php";
        $stop_simulate_url = $this->get_config($conf_file, "simulate_url");
        $header = array
        (
            "Content-Type:application/json"
        );
        $post_data = array
        (
            'action' => "start"
        );
        $content = json_encode($post_data);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $stop_simulate_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

        $res = curl_exec($curl);
        curl_close($curl);
		if($res == true)
		{
			$result = array('status' => 'success');
			echo json_encode($result);
		}
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
    
}