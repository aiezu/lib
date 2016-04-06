<?php
//毫秒获取函数
function getmicrotime(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

//单线程CURL抓取
function http($url, $params, $method='get') {
    $result = array();
    $BeginTime = getmicrotime();
    $ch = curl_init();
    $header = array(
    );
    if ( strtolower($method) == 'get' ) {
	if ( strpos($url, "?") > 0 ) {
    	    parse_str(substr($url, strpos($url, "?")+1), $fields);
	    $url = sprintf("%s?%s", substr($url, 0, strpos($url, "?")), http_build_query(array_merge($fields, $params["data"])));
	} else {
	    $url = sprintf("%s?%s", $url, http_build_query($params["data"]));
	}
        curl_setopt($ch, CURLOPT_URL, $url);
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params["data"]);
        curl_setopt($ch, CURLOPT_POST, 1);
    }
    if( isset($params["proxy"]) ) {
        curl_setopt($ch, CURLOPT_PROXYTYPE, $params["proxy"]["type"]);
        curl_setopt($ch, CURLOPT_PROXY, sprintf("%s:%s", $params["proxy"]["host"], $params["proxy"]["port"]));
    }
    isset($params["Cookie"])  && $header[] =  sprintf("Cookie: %s", $params["Cookie"]);
    isset($params["User-Agent"])  && $header[] =  sprintf("User-Agent: %s", $params["User-Agent"]);
    isset($params["Referer"])  && $header[] =  sprintf("Referer: %s", $params["Referer"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
    $result["response"] = curl_exec($ch);
    isset($params["code"]) && $result["response"] = iconv($params["code"], "UTF-8", $result["response"]);
    $result["result"] = curl_errno($ch);
    $result["errmg"] = curl_error($ch);
    $result["timelong"] = intval((getmicrotime()-$BeginTime)*1000);
    curl_close($ch);
    return $result;
}


//并发多线程CURL抓取
function http_multi( $urls ){
    $mh = curl_multi_init();
    $handles = array();
    foreach($urls as $id=>&$url){
        $header = array();
        $curl = curl_init();
        if ( strtolower($url["method"]) == "post" ){
            curl_setopt($curl, CURLOPT_URL, $url["url"]);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $url["data"]);
        } else {
            curl_setopt($curl, CURLOPT_URL, "{$url["url"]}?" . http_build_query($url["data"]));
        }
        if ( isset($url["cookies"]) ) $header[] = "Cookie: {$url["cookies"]}";
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        if( isset($url["proxy"]) ) {
            curl_setopt($curl, CURLOPT_PROXYTYPE, $url["proxy"]["type"]);
            curl_setopt($curl, CURLOPT_PROXY, "{$url['proxy']['host']}:{$url['proxy']['port']}");
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_multi_add_handle($mh,$curl);
        $handles[$curl] = $id;
        $url["handle"] = $curl;
    }

    $BeginTime = getmicrotime();
    $running=null;
    do {
        curl_multi_exec($mh,$running);
        usleep(10000);
        while( ($ret = curl_multi_info_read($mh))!==false ){
            $id = $handles[$ret["handle"]];
            $urls[$id]["msg"] = $ret["msg"];
            $urls[$id]["result"] = $ret["result"];
            $urls[$id]["timelong"] = intval((getmicrotime()-$BeginTime)*1000);
        }
    } while ($running > 0);

    foreach($urls as $id => &$url) {
        $url["response"] = trim(curl_multi_getcontent($url["handle"]));
        curl_multi_remove_handle($mh, $url["handle"]);
        unset($url["handle"]);
    }
    curl_multi_close($mh);
    return $urls;
}
