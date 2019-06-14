<?php
setlocale(LC_ALL,"US");
date_default_timezone_set('Asia/Shanghai');

$room_id = $argc > 1 ? $argv[1] : 288016;

$dy = new idDY();
$data = $dy->GetRoomInfo($room_id);

echo "Room ID -> {$data["rid"]}" . PHP_EOL;
echo "Room name -> {$data["name"]}" . PHP_EOL;
echo "Online -> {$data["online"]}" . PHP_EOL;
echo "Room user -> {$data["user"]}" . PHP_EOL;

if($data["status"] != 1)
{
    echo "Room is not live";
    exit;
}

echo "Room is living. Run danmaku fetcher...";

idDYDanmaku::Boot($room_id);
exit;

//$data2 = $dy->GetLiveCategory(1, 1, 10);
//var_dump($data2);

//$data3 = $dy->GetGameChannels();
//var_dump($data3);


class idDY
{
    public static $ID_API = [
            "ROOM_INFO" => "http://open.douyucdn.cn/api/RoomApi/room/%s",
            "LIVE_CATEGORY" => "http://open.douyucdn.cn/api/RoomApi/live",
            "GAME_CHANNELS" => "http://open.douyucdn.cn/api/RoomApi/game",
        ];

    public $last_errno = 0;
    public $last_error = "";

    public function __construct()
    {
    }

    private function MakeQueryString($params)
    {
        $r = [];
        foreach($params as $k => $v)
        {
            $r[] = "$k=" . urlencode($v);
        }
        return implode("&", $r);
    }

    private function Get($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1 );

        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }

    private function Post($url, $data = "")
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1 );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }

    public function GetRoomInfo($rid)
    {
        $url = sprintf(static::$ID_API["ROOM_INFO"], $rid);
        $r = $this->Get($url);
        if(!$r)
        {
            $this->SetLastError(-1);
            return false;
        }

        $room_info = json_decode($r, true);
        //var_dump($room_info);
        if($room_info["error"] != 0)
        {
            $this->SetLastError($room_info["error"] , $room_info["data"] );
            return false;
        }

        $data = $room_info["data"];
        $res = [
            "rid" => $data["room_id"],
            "cate_id" => $data["cate_id"],
            "name" => $data["room_name"],
            "online" => $data["online"],
            "user" => $data["owner_name"],
            "status" => $data["room_status"],
        ];
        return $res;
    }

    public function GetLiveCategory($cate = false, $page_no = false, $page_size = false)
    {
        $url = static::$ID_API["LIVE_CATEGORY"];
        if($cate !== false)
            $url .= "/{$cate}";

        $p = $this->MakePageReq($page_no, $page_size);
        $q = [];
        if($p["offset"] !== false)
            $q["offset"] = $p["offset"];
        if($p["limit"] !== false)
            $q["limit"] = $p["limit"];
        $qstr = $this->MakeQueryString($q);
        if(strlen($qstr))
            $url .= "?" . $qstr;
        $r = $this->Get($url);
        if(!$r)
        {
            $this->SetLastError(-1);
            return false;
        }
        $lives = json_decode($r, true);
        if($lives["error"] != 0)
        {
            $this->SetLastError($lives["error"] , $lives["data"] );
            return false;
        }
        //var_dump($lives);
        $data = $lives["data"];
        $res = [];
        foreach($data as $v)
        {
            $res[] = [
                "rid" => $v["room_id"],
                "name" => $v["room_name"],
                "preview" => $v["room_src"],
                "user" => $v["nickname"],
                "online" => $v["online"],
                "user_id" => $v["owner_uid"],
                "url" => $v["url"],
            ];
        }
        return $res;
    }

    public function GetGameChannels()
    {
        $url = static::$ID_API["GAME_CHANNELS"];

        $r = $this->Get($url);
        if(!$r)
        {
            $this->SetLastError(-1);
            return false;
        }
        $channels = json_decode($r, true);
        if($channels["error"] != 0)
        {
            $this->SetLastError($channels["error"] , $channels["data"] );
            return false;
        }
        //var_dump($channels);
        $data = $channels["data"];
        $res = [];
        foreach($data as $v)
        {
            $res[] = [
                "cate_id" => $v["cate_id"],
                "name" => $v["game_name"],
                "preview" => $v["game_src"],
                "icon" => $v["game_icon"],
                "url" => $v["game_url"],
            ];
        }
        return $res;
    }

    private function MakePageReq($pn, $ps)
    {
        $r = [
            "offset" => false,
            "limit" => false,
        ];
        if($pn === false)
        {
            return $r;
        }
        if($ps === false)
        {
            $r["offset"] = $pn;
            return $r;
        }
        $offset = ($pn - 1) * $ps;
        $limit = $ps;
        $r["offset"] = $offset;
        $r["limit"] = $limit;
        return $r;
    }

    private function SetLastError($errno, $error = "")
    {
        $this->last_errno = $errno;
        $this->last_error = $error ? $error : $this->GetErrorString($errno);
    }

    public function GetErrorString($err)
    {
        static $DY_Error = [
            "101" => "Room is not exists",
            "102" => "Room is not active",
            "103" => "Getting room error",

            "501" => "Category is not exists",
            "999" => "API is not publish",
        ];
        static $Error = [
            "No error",
            "cURL error",
        ];
        if($err <= 0)
        {
            $e = -$err;
            return $Error[$e];
        }
        else
        {
            return $DY_Error["" . $err];
        }
    }

}

class idDYDanmaku
{
    public static $ID_MSG_CLIENT_TO_SERVER = 689;
    public static $ID_MSG_SERVER_TO_CLIENT = 690;
    public static $ID_DY_VER = "20190612";
    public static $ID_PINGPONG_INTERVAL = 45;

    public $interval = 2;
    public $pingpong_interval = 30;
    public $sock = null;
    public $rid = 0;

    private $m_lastPingpong = 0;

    public function __construct($rid)
    {
        $this->rid = $rid;
    }

    public function Start()
    {
        $gid = -9999;
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $r = socket_connect($this->sock, "119.96.201.28", "8601");

        // send login room request
        $len = $this->SendData("type@=loginreq/roomid@={$this->rid}/ver@=" . static::$ID_DY_VER . "/ct@=0/");
        if(!$len)
            return false;

        // recv server response
        $data = [];
        $len = $this->RecvData($this->sock, $data);
        if(!$len)
            return false;

        // send join a danmaku group request
        $msg = $this->PackData("type@=joingroup/rid@={$this->rid}/gid@={$gid}/");
        $len = socket_send($this->sock, $msg, strlen($msg), 0);
        if(!$len)
            return false;

        $this->m_lastPingpong = 0;
        return true;
    }

    public function Run()
    {
        if(!$this->sock)
            return;

        while(1)
        {
            $read = [$this->sock];
            $write = [];
            $except = [];
            $r = socket_select($read, $write, $except, $this->interval);
            if(!$r)
            {
                sleep($this->interval);
                $this->Pingpong(); // single thread
                continue;
            }
            $data = [];
            $len = $this->RecvData($read[0], $data);
            if($len)
            {
                if($data["type"] !== "chatmsg")
                    print_r($data);
                else
                {
                    $dm = [
                        "type" => $data["type"],
                        "room_id" => $data["rid"],
                        "user_id" => $data["uid"],
                        "user_name" => $data["nn"],
                        "content" => $data["txt"],
                    ];
                    print_r($dm);
                }
            }
            else
            {
                echo "none buf" . PHP_EOL;
                sleep($this->interval);
            }
            $this->Pingpong(); // single thread
        }
    }

    public function Pingpong()
    {
        if(!$this->sock)
            return;

        $ts = time();
        if(!$this->m_lastPingpong)
            $this->m_lastPingpong = $ts;

        if($ts - $this->m_lastPingpong < $this->pingpong_interval)
            return;

        $len = $this->SendData("type@=keeplive/tick@={$ts}/");
        $this->m_lastPingpong = $ts;
        if(!$len)
            echo "ping pong fail";
    }

    public function Stop()
    {
        if(!$this->sock)
            return;

        $len = $this->SendData("type@=logout/");
        if(!$len)
            echo "logout fail";

        socket_close($this->sock);
        $this->sock = null;
    }

    public static function Boot($rid)
    {
        $dm = new idDYDanmaku($rid);
        if($dm->Start())
        {
            $dm->Run();
            $dm->Stop();
        }
    }

    public function __destruct()
    {
        if($this->sock)
            socket_close($this->sock);
        if($this->thread)
            $this->thread->kill();
    }

    protected function SendData($data)
    {
        if(!$this->sock)
            return false;

        $msg = $this->PackData($data);
        $len = socket_send($this->sock, $msg, strlen($msg), 0);

        return $len;
    }

    protected function RecvData($s = null, &$data = null)
    {
        $sock = $s ?: $this->sock;

        if(!$sock)
            return false;

        $len = socket_recv($sock, $buf, 2048, 0);
        if($len === false)
        {
            echo "socket_recv() failed -> " . socket_last_error() . " = " . socket_strerror(socket_last_error()) . PHP_EOL;
        }
        else
        {
            if($data !== null)
            {
                $data = $this->UnpackData($buf);
            }
        }

        //echo $buf;
        return $len;
    }

    protected function PackData($msg)
    {
        $len = strlen($msg) + 9;

        $r = pack("LLScca*c", $len, $len, static::$ID_MSG_CLIENT_TO_SERVER, 0, 0, $msg, 0);
        /*
        $a = $this->ASCIIEncode($msg);
        foreach($a as $v)
        {
            $r .= pack("C", $v);
        }
        */

        //echo $r;
        return $r;
    }

    protected function UnpackData($msg)
    {
        $data = unpack("L_length/L_length_1/S_type/c_unused1/c_unused2/a*_msg", $msg);
        $arr = explode("/", $data["_msg"]);
        $r = [];
        foreach($arr as $v)
        {
            $a = explode("@=", $v);
            $r[$a[0]] = isset($a[1]) ? $a[1] : "";
        }
        //print_r($r);

        return $r;
    }

    private function ASCIIEncode($c) {
        $len = strlen($c);
        $a = 0;
        $scill = [];
        while ($a < $len) {
            $ud = 0;
            if (ord($c{$a}) >= 0 && ord($c{$a}) <= 127) {
                $ud = ord($c{$a});
                $a += 1;
            } else if (ord($c{$a}) >= 192 && ord($c{$a}) <= 223) {
                $ud = (ord($c{$a}) - 192) * 64 + (ord($c{$a + 1}) - 128);
                $a += 2;
            } else if (ord($c{$a}) >= 224 && ord($c{$a}) <= 239) {
                $ud = (ord($c{$a}) - 224) * 4096 + (ord($c{$a + 1}) - 128) * 64 + (ord($c{$a + 2}) - 128);
                $a += 3;
            } else if (ord($c{$a}) >= 240 && ord($c{$a}) <= 247) {
                $ud = (ord($c{$a}) - 240) * 262144 + (ord($c{$a + 1}) - 128) * 4096 + (ord($c{$a + 2}) - 128) * 64 + (ord($c{$a + 3}) - 128);
                $a += 4;
            } else if (ord($c{$a}) >= 248 && ord($c{$a}) <= 251) {
                $ud = (ord($c{$a}) - 248) * 16777216 + (ord($c{$a + 1}) - 128) * 262144 + (ord($c{$a + 2}) - 128) * 4096 + (ord($c{$a + 3}) - 128) * 64 + (ord($c{$a + 4}) - 128);
                $a += 5;
            } else if (ord($c{$a}) >= 252 && ord($c{$a}) <= 253) {
                $ud = (ord($c{$a}) - 252) * 1073741824 + (ord($c{$a + 1}) - 128) * 16777216 + (ord($c{$a + 2}) - 128) * 262144 + (ord($c{$a + 3}) - 128) * 4096 + (ord($c{$a + 4}) - 128) * 64 + (ord($c{$a + 5}) - 128);
                $a += 6;
            } else if (ord($c{$a}) >= 254 && ord($c{$a}) <= 255) { //error
                $ud = false;
            }
            $scill[] = dechex($ud);
        }
        return $scill;
    }
}