<?php
class order_controller extends general_controller
{
    public function action_confirm()
    {
        $user_id = $this->is_logined();
        $cart = json_decode(stripslashes(request('CARTS', null, 'cookie')), TRUE);
        if($cart)
        {
            $goods_model = new goods_model();
            $this->cart = $goods_model->get_cart_items($cart);
            $consignee_model = new user_consignee_model();
            $this->consignee_list = $consignee_model->get_user_consignee_list($user_id);
            $this->shipping_method_list = vcache::instance()->shipping_method_model('indexed_list');
            $this->payment_method_list = vcache::instance()->payment_method_model('indexed_list');
            $this->compiler('order_confirm.html');
        }
        else
        {
            jump(url('cart', 'index'));
        }
    }
    
    public function action_submit()
    {
        $user_id = $this->is_logined();
        //检查购物车信息
        $cart = json_decode(stripslashes(request('CARTS', null, 'cookie')), TRUE);
        if(!$cart) $this->prompt('error', '无法获取购物车数据');
        $goods_model = new goods_model();
        if(!$cart = $goods_model->get_cart_items($cart)) $this->prompt('error', '购物车商品数据不正确');
        //检查收件人信息
        $csn_id = (int)request('csn_id', 0);
        $consignee_model = new user_consignee_model();
        if(!$consignee = $consignee_model->find(array('id' => $csn_id, 'user_id' => $user_id))) $this->prompt('error', '无法获取收件人地址信息');
        //检查配送方式
        $shipping_id = (int)request('shipping_id', 0);
        $shipping_map = vcache::instance()->shipping_method_model('indexed_list');
        if(!isset($shipping_map[$shipping_id])) $this->prompt('error', '配送方式不存在');
        //检查运费
        $shipping_model = new shipping_method_model();
        $shipping_amount = $shipping_model->check_freight($user_id, $shipping_id, $consignee['province'], $cart);
        if(FALSE === $shipping_amount) $this->prompt('error', '计算运费失败');
        //检查付款方式
        $payment_id = (int)request('payment_id', 0);
        $payment_map = vcache::instance()->payment_method_model('indexed_list');
        if(!isset($payment_map[$payment_id]))
        {
            $payment_id = current($payment_map);
            $payment_id = $payment_id['id'];
        }
        //创建订单
        $order_model = new order_model();
        $data = array
        (
            'order_id' => $order_model->create_order_id(),
            'user_id' => $user_id,
            'shipping_method' => $shipping_id,
            'payment_method' => $payment_id,
            'goods_amount' => $cart['amount'],
            'shipping_amount' => $shipping_amount,
            'order_amount' => $cart['amount'] + $shipping_amount,
            'memos' => trim(strip_tags(request('memos', ''))),
            'created_date' => $_SERVER['REQUEST_TIME'],
            'order_status' => 1,
        );
        
        if($order_model->create($data))
        {
            $result = $this->dms_op($data);
//			$result = true;
			if($result == true)
			{
				$order_goods_model = new order_goods_model();
				$order_goods_model->add_records($data['order_id'], $cart['items']);
				$order_consignee_model = new order_consignee_model();
				$order_consignee_model->add_records($data['order_id'], $consignee);
				setcookie('CARTS', null, $_SERVER['REQUEST_TIME'] - 3600, '/');
				jump(url('pay', 'index', array('order_id' => $data['order_id'])));
			}
			else
			{
				$this->prompt('error', '创建订单失败，请稍后重试');
			}
        }
        else
        {
            $this->prompt('error', '创建订单失败，请稍后重试');
        }
    }

    public function dms_op($data)
    {
        $conf_file = "install/resources/config_dms.php";

//        $res = $this -> http_op($data, $conf_file);
//        $res = $this -> mq_op($data, $conf_file);
		$res = $this -> score_op($data, $conf_file);
		return $res;
    }

    public function http_op($data, $conf_file)
    {
        $http_url_score = $this -> get_config($conf_file, "http_url_score");
		$http_url_message = $this -> get_config($conf_file, "http_url_message");
        $header = array
        (
            "Content-Type:application/json"
        );
        //$post_data = array
        //(
        //    'data' => $data
        //);
        //$content = json_encode($post_data);
		$content = $this -> reform_data($data);
		
        $curl_score = curl_init();
        curl_setopt($curl_score, CURLOPT_URL, $http_url_score);
//        curl_setopt($curl_score, CURLOPT_SSL_VERIFYPEER, 0);
//        curl_setopt($curl_score, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl_score, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl_score, CURLOPT_HEADER, true);
        curl_setopt($curl_score, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_score, CURLOPT_POST, 1);
        curl_setopt($curl_score, CURLOPT_POSTFIELDS, $content);

        $res_score = curl_exec($curl_score);
        curl_close($curl_score);
		
		if($res_score == false)
		{
			return false;
		}
		
		$curl_message = curl_init();
        curl_setopt($curl_message, CURLOPT_URL, $http_url_message);
//        curl_setopt($curl_message, CURLOPT_SSL_VERIFYPEER, 0);
//        curl_setopt($curl_message, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl_message, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl_message, CURLOPT_HEADER, true);
        curl_setopt($curl_message, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_message, CURLOPT_POST, 1);
        curl_setopt($curl_message, CURLOPT_POSTFIELDS, $content);

        $res_notice = curl_exec($curl_message);
        curl_close($curl_message);

		if($res_notice == false)
		{
			return false;
		}
		
		return true;
    }
	
	public function score_op($data, $conf_file)
	{
		$score_op_url = $this->get_config($conf_file, "score_op_url");
		$header = array
        (
            "Content-Type:application/json"
        );
		$content = $this -> reform_data($data);
		
        $curl_op = curl_init();
        curl_setopt($curl_op, CURLOPT_URL, $score_op_url);
        curl_setopt($curl_op, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl_op, CURLOPT_HEADER, true);
        curl_setopt($curl_op, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_op, CURLOPT_POST, 1);
        curl_setopt($curl_op, CURLOPT_POSTFIELDS, $content);

        $res_op = curl_exec($curl_op);
		$code = curl_getinfo($curl_op, CURLINFO_HTTP_CODE);
        curl_close($curl_op);
		if($code > 300)
		{
			return false;
		}
		return true;
	}

    public function mq_op($data, $conf_file)
    {
        $iam_endpoint = $this->get_config($conf_file, "iam_endpoint");
        $dms_endpoint = $this->get_config($conf_file, "dms_endpoint");
        $username = $this->get_config($conf_file, "username");
        $password = $this->get_config($conf_file, "password");
        $project_id = $this->get_config($conf_file, "project_id");
        $queue_id = $this->get_config($conf_file, "queue_id");
        $group_id = $this->get_config($conf_file, "group_id");

        $reformed_data = $this -> reform_data($data);

//        $token = $this -> get_token($iam_endpoint, $username, $password, $project_id);
//		file_put_contents("/tmp/test_mq.log", order_controller::$token, FILE_APPEND);
		$token = $this->get_config($conf_file, "g_token");
//		file_put_contents("/tmp/test_mq.log", $token, FILE_APPEND);
        $res = $this -> produce($reformed_data, $dms_endpoint, $token, $project_id, $queue_id);
		if($res == false)
		{
			file_put_contents("/tmp/test_mq2.log", "a\n", FILE_APPEND);
			$token = $this -> get_token($iam_endpoint, $username, $password, $project_id);
			$this->update_config($conf_file, "g_token", $token);
			return $this -> produce($reformed_data, $dms_endpoint, $token, $project_id, $queue_id);
		}
		return true;
//        $this -> consume($dms_endpoint, $token, $project_id, $queue_id, $group_id);
//		return true;
    }

    function reform_data($data)
    {
        $user_id = $data['user_id'];
        $order_id = $data['order_id'];
        $order_amount = $data['order_amount'];

        $reformed_data = array
        (
            'userId' => $user_id,
            'orderId' => $order_id,
            'orderAmount' => $order_amount
        );
        return json_encode($reformed_data);
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

    public function get_token($iam_endpoint, $username, $password, $project_id)
    {
        $token_url = "https://{$iam_endpoint}/v3/auth/tokens";
        $post_array = array
        (
            'auth' => array
            (
                'identity' => array
                (
                    'methods' => array
                    (
                        "password"
                    ),
                    'password' => array
                    (
                        'user' => array
                        (
                            'name' => $username,
                            'password' => $password,
                            'domain' => array
                            (
                                'name' => $username
                            )
                        )
                    )
                ),
                'scope' => array
                (
                    'project' => array
                    (
                        'id' => $project_id
                    )
                )
            )
        );
        $content = json_encode($post_array);
        $header = array(
            "Content-Type:application/json"
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $token_url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

        $res = curl_exec($curl);
        $headerSize = curl_getinfo($curl,  CURLINFO_HEADER_SIZE);
        $headerStr = substr($res, 0, $headerSize);
        curl_close($curl);
        $headers = explode("\n", $headerStr);
        foreach ($headers as $item) {
            if (strpos($item, "X-Subject-Token") !== false) {
                $index = strpos($item, ":");
                $token = substr($item, $index + 1);
                return $token;
            }
        }
    }

    public function produce($data, $dms_endpoint, $token, $project_id, $queue_id)
    {
        $url = "https://{$dms_endpoint}/v1.0/{$project_id}/queues/{$queue_id}/messages";
        $request_data = array(
            'messages' => array(
                array(
                    "body" => $data
                )
            )
        );
        $post_array = json_encode($request_data);
        $token_item = "X-Auth-Token:{$token}";
        $header = array
        (
            "Content-Type:application/json",
            $token_item
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_array);

        curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($code > 300)
		{
			//file_put_contents("/tmp/test_mq.log", $code, FILE_APPEND);
			return false;
		}
		return true;
//        print_r($res);
    }

    public function consume($dms_endpoint, $token, $project_id, $queue_id, $group_id)
    {
        $consume_url = "https://{$dms_endpoint}/v1.0/{$project_id}/queues/{$queue_id}/groups/{$group_id}/messages";
        $token_item = "X-Auth-Token:{$token}";
        $header = array
        (
            "Content-Type:application/json",
            $token_item
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $consume_url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $consume_res = curl_exec($curl);
        curl_close($curl);
        $this -> ack($consume_res, $dms_endpoint, $token, $project_id, $queue_id, $group_id);
    }

    public function ack($consume_res, $dms_endpoint, $token, $project_id, $queue_id, $group_id)
    {
        $ack_url = "https://{$dms_endpoint}/v1.0/{$project_id}/queues/{$queue_id}/groups/{$group_id}/ack";
        $res_json = json_decode($consume_res, true);
        $message = array();
        foreach ($res_json as $response)
        {
            $handler = $response["handler"];
            $arr_item = array(
                'handler' => $handler,
                'status' => "success"
            );
            array_push($message, $arr_item);
        }
        $messages = array(
            'message' => $message
        );
        $ack_array = json_encode($messages);
        $token_item = "X-Auth-Token:{$token}";
        $header = array
        (
            "Content-Type:application/json",
            $token_item
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $ack_url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl   , CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $ack_array);
        $res = curl_exec($curl);
        curl_close($curl);
//        print_r($res);
    }
    
    public function action_list()
    {
        $user_id = $this->is_logined();
        $order_model = new order_model();
        $page_id = request('page', 1);
        if($order_list = $order_model->find_all(array('user_id' => $user_id), 'created_date DESC', '*', array($page_id, 10)))
        {
            $order_goods_model = new order_goods_model();
            foreach($order_list as &$v) $v['goods_list'] = $order_goods_model->get_goods_list($v['order_id']);
        }
                
        $this->order_list = array('rows' => $order_list, 'paging' => $order_model->page);
        $this->payment_map = vcache::instance()->payment_method_model('indexed_list');
        $this->compiler('user_order_list.html');
    }
    
    public function action_view()
    {
        $user_id = $this->is_logined();
        $order_id = bigintstr(request('id'));
        $order_model = new order_model();
        if($order = $order_model->find(array('order_id' => $order_id, 'user_id' => $user_id)))
        {
            $vcache = vcache::instance();
            $payment_map = $vcache->payment_method_model('indexed_list');
            $shipping_map = $vcache->shipping_method_model('indexed_list');
            $order['payment_method_name'] = $payment_map[$order['payment_method']]['name'];
            $order['shipping_method_name'] = $shipping_map[$order['shipping_method']]['name'];
            
            
            $condition = array('order_id' => $order_id);
            $consignee_model = new order_consignee_model();
            $this->consignee = $consignee_model->find($condition);
            
            $order_goods_model = new order_goods_model();
            $this->goods_list = $order_goods_model->get_goods_list($order_id);
            
            $this->progress = $order_model->get_user_order_progress($order['order_status'], $order['payment_method']);
            $this->status_map = $order_model->status_map;
            
            if($order['order_status'] == 1 && $order['payment_method'] != 2)
            {
                if(!$this->countdown = $order_model->is_overdue($order_id, $order['created_date'])) $order['order_status'] = 0;
            }
            elseif($order['order_status'] == 3)
            {
                $shipping_model = new order_shipping_model();
                if($shipping = $shipping_model->find($condition, 'dateline DESC'))
                {
                    $this->countdown = intval($shipping['dateline'] + $GLOBALS['cfg']['order_delivery_expires'] * 86400 - $_SERVER['REQUEST_TIME']);
                    if(!$this->countdown) $order_model->update($condition, array('order_status' => 4));
                    $this->shipping = $shipping;
                    $carrier_map = $vcache->shipping_carrier_model('indexed_list');
                    $this->carrier = $carrier_map[$shipping['carrier_id']];
                }
            }
            
            $this->order = $order;
            $this->compiler('user_order_details.html');
        }
        else
        {
            jump(url('main', '404'));
        }
    }
    
    public function action_cancel()
    {
        $user_id = $this->is_logined();
        $order_id = bigintstr(request('id'));
        $order_model = new order_model();
        if($order = $order_model->find(array('order_id' => $order_id, 'user_id' => $user_id)))
        {
            if($order['order_status'] == 1)
            {
                $order_model->update(array('order_id' => $order_id), array('order_status' => 0));
                $order_goods_model = new order_goods_model();
                $order_goods_model->restocking($order_id);
                $this->prompt('success', '取消订单成功', url('order', 'view', array('id' => $order_id)));
            }
            else
            {
                $this->prompt('error', '参数非法');
            }
        }
        else
        {
            jump(url('main', '404'));
        }
    }
    
    public function action_delivered()
    {
        $user_id = $this->is_logined();
        $order_id = bigintstr(request('id'));
        $order_model = new order_model();
        if($order = $order_model->find(array('order_id' => $order_id, 'user_id' => $user_id)))
        {
            if($order['order_status'] == 3)
            {
                $order_model->update(array('order_id' => $order_id), array('order_status' => 4));
                $this->prompt('success', '签收成功，感谢您的购买！如有任何售后问题请及时与客服联系', url('order', 'view', array('id' => $order_id)), 5);
            }
            else
            {
                $this->prompt('error', '参数非法');
            }
        }
        
        jump(url('main', '404'));
    }
    
    public function action_rebuy()
    {
        $user_id = $this->is_logined();
        $order_id = bigintstr(request('id'));
        $order_model = new order_model();
        if($order_model->find(array('order_id' => $order_id, 'user_id' => $user_id)))
        {
            if($cart = request('CARTS', array(), 'cookie')) $cart = json_decode($cart, TRUE);
            
            $order_goods_model = new order_goods_model();
            $opts_model = new order_goods_optional_model();
            $goods_list = $order_goods_model->find_all(array('order_id' => $order_id), null, 'id, goods_id, goods_qty');
            foreach($goods_list as $v)
            {
                $key = $v['goods_id'];
                $opt_ids = null;
                if($opts = $opts_model->find_all(array('map_id' => $v['id'])))
                {
                    $opts = array_column($opts, 'opt_id');
                    $key .= implode('_', $opts);
                }
                $cart[$key] = array('id' => $v['goods_id'], 'qty' => $v['goods_qty'], 'opts' => $opts);
            }
            setcookie('CARTS', json_encode($cart), $_SERVER['REQUEST_TIME'] + 604800, '/');
            jump(url('cart', 'index'));
        }
        
        jump(url('main', '404'));
    }
}