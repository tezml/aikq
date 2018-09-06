<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/7
 * Time: 11:40
 */

namespace App\Http\Controllers\PC\Live;

use App\Console\LiveDetailCommand;
use App\Console\NoStartPlayerJsonCommand;
use App\Http\Controllers\IntF\AikanQController;
use App\Http\Controllers\IntF\CmsController;
use App\Http\Controllers\PC\CommonTool;
use App\Models\Article\PcArticle;
use App\Models\LgMatch\Match;
use App\Models\LgMatch\MatchLive;
use App\Models\Match\MatchLiveChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Cookie;

class LiveController extends Controller
{

    const BET_MATCH = 1;
    const LIVE_HD_CODE_KEY = 'LIVE_HD_CODE_KEY';

    /**
     * 首页（首页、竞彩、足球、篮球）缓存
     * @param Request $request
     */
    public function staticIndex(Request $request){
        $this->livesStatic($request);
        $this->businessStatic($request);
    }

    /**
     * 竞彩缓存
     * @param Request $request
     */
    public function betLivesStatic(Request $request){
        $html = $this->betLives(new Request());
        try {
            Storage::disk("public")->put("/static/betting.html",$html);
        } catch (\Exception $exception) {
            echo $exception->getMessage();
        }
    }

    /**
     * 静态化站长合作
     * @param Request $request
     */
    public function businessStatic(Request $request) {
        $businessHtml = $this->business($request);
        try {
            Storage::disk("public")->put("/www/business.html", $businessHtml);
        } catch (\Exception $exception) {
            echo $exception->getMessage();
        }
    }

    /**
     * 首页缓存
     * @param Request $request
     */
    public function livesStatic(Request $request){
        $html = $this->lives(new Request());
        try {
            Storage::disk("public")->put("/www/index.html",$html);
        } catch (\Exception $exception) {
            echo $exception->getMessage();
        }
    }

    /**
     * 首页、竞彩赛事
     * @param $bet
     * @return mixed
     */
    protected function liveJson($bet = '') {
        try {
            $aiCon = new AikanQController();
            $jsonObj = $aiCon->livesJson(new Request())->getData();
            $server_output = json_encode($jsonObj);
            if ($bet == self::BET_MATCH) {
                Storage::disk("public")->put("/static/json/bet-lives.json", $server_output);
            } else{
                Storage::disk("public")->put("/static/json/lives.json", $server_output);
                Storage::disk("public")->put("/app/v101/lives.json", $server_output);
                Storage::disk("public")->put("/app/v110/lives.json", $server_output);
            }
        } catch (\Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * 篮球赛事json缓存
     */
    protected function basketballLiveJson() {
        try {
            $aikCon = new AikanQController();
            $livesJson = $aikCon->basketballLivesJsonData(false);
            Storage::disk("public")->put("/static/json/basketball-lives.json", json_encode($livesJson));
        } catch (\Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * 足球赛事json缓存
     */
    protected function footballLiveJson() {
        try {
            $aikCon = new AikanQController();
            $livesJson = $aikCon->footballLivesJsonData(false);
            Storage::disk("public")->put("/static/json/football-lives.json", json_encode($livesJson));
        } catch (\Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * PC直播赛事的json
     * @param Request $request
     */
    public function allLiveJsonStatic(Request $request) {
        $this->liveJson();//首页赛事缓存
    }

    //===============================================================================//

    /**
     * 播放失败
     * @param Request $request
     * @return json
     */
    public function liveError(Request $request){
        $cid = $request->input('cid',0);
        if ($cid <= 0){
            return;
        }
        $akqCon = new AikanQController();
        $json = $akqCon->saveLivesError($cid);
        return $json;
    }

    /**
     * 首页直播列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function lives(Request $request) {
        //$json = $this->getLives();
        $cache = Storage::get('/public/static/json/lives.json');
        $json = json_decode($cache, true);
        if (is_null($json)){
            //return abort(404);
        }

        $articles = PcArticle::indexArticles();

        $json['week_array'] = array('星期日','星期一','星期二','星期三','星期四','星期五','星期六');
        $json['check'] = 'all';
        $json['arts'] = $articles;
        $json['isIndex'] = true;
//        dump($articles);
        return view('pc.home', $json);
    }

    /**
     * 商务合作
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function business(Request $request) {
        $cache = Storage::get('/public/static/json/lives.json');
        $json = json_decode($cache, true);
        if (is_null($json)){
            //return abort(404);
        }
        $json['week_array'] = array('星期日','星期一','星期二','星期三','星期四','星期五','星期六');
        $json['title'] = "视频调用_爱看球";
        $json['check'] = 'business';
        return view('pc.business', $json);
    }

    /**
     * 竞彩直播比赛列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function betLives(Request $request) {
        //$json = $this->getLives(self::BET_MATCH);
        $cache = Storage::get('/public/static/json/bet-lives.json');
        $json = json_decode($cache, true);
        if (is_null($json)){
            return abort(404);
        }
        $json['check'] = 'bet';
        $json['week_array'] = array('星期日','星期一','星期二','星期三','星期四','星期五','星期六');
        return view('pc.home', $json);
    }

    /**
     * 获取有直播的比赛。
     * @param string $bet
     * @return mixed
     */
    protected function getLives($bet = '') {
        $akqCon = new AikanQController();
        $json = $akqCon->livesJsonData($bet);
        return $json;
    }

    /**
     * 拼凑新的返回对象
     * @param $json
     * @return mixed
     */
    protected function toNewMatchArray($json) {
        if (is_null($json)) return null;

        $start_matches = [];//比赛中的赛事
        $wait_matches = [];//稍后的比赛

        $matches = $json['matches'];
        foreach ($matches as $time=>$match_array) {
            $w_match = [];
            foreach ($match_array as $index=>$match) {
                if ($match['isMatching']) {
                    $start_matches[] = $match;
                } else {
                    $w_match[] = $match;
                }
            }
            if (count($w_match) > 0) {
                $wait_matches[$time] = $w_match;
            }
        }

        $result['play_matches'] = $start_matches;
        $result['matches'] = $wait_matches;
        return $result;
    }

    /**
     * 首页直播列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function basketballLives(Request $request) {
        $cache = Storage::get('/public/static/json/basketball-lives.json');
        $json = json_decode($cache, true);
        if (is_null($json)){
            return abort(404);
        }
        $json['check'] = 'basket';
        $json['week_array'] = array('星期日','星期一','星期二','星期三','星期四','星期五','星期六');
        return view('pc.home', $json);
    }

    /**
     * 首页直播列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function footballLives(Request $request) {
        $cache = Storage::get('/public/static/json/football-lives.json');
        $json = json_decode($cache, true);
        if (is_null($json)){
            return abort(404);
        }
        $json['check'] = 'foot';
        $json['week_array'] = array('星期日','星期一','星期二','星期三','星期四','星期五','星期六');
        return view('pc.home', $json);
    }

    /**
     * 直播终端
     * @param Request $request
     * @param $param
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function detail(Request $request, $param) {
        preg_match("/(\d)(\d+)/", $param, $matches);
        if (count($matches) != 3) {
            return abort(404);
        }
        $sport = $matches[1];
        $id = $matches[2];
        if ($sport == MatchLive::kSportFootball) {
            return $this->footballDetail($request, $id, true);
        } else if ($sport == MatchLive::kSportBasketball) {
            return $this->basketDetail($request, $id, true);
        } else if ($sport == MatchLive::kSportSelfMatch) {
            return $this->otherDetail($request, $id, true);
        }
        return abort(404);
    }

    /**
     * @param Request $request
     * @param $name_en
     * @param $param
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function detailBySL(Request $request, $name_en, $param) {
        return $this->detail($request, $param);
    }

    /**
     * 足球直播终端
     * @param Request $request
     * @param $id
     * @param bool $immediate
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function footballDetail(Request $request, $id, $immediate = false) {
        $akqCon = new AikanQController();
        $json = $akqCon->detailJsonData($id, false);
        $json['articles'] = PcArticle::randArticles(12);
        return $this->detailHtml($json, $id);
    }

    /**
     * 页面html内容
     * @param $json
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function detailHtml($json, $id) {
        if (!isset($json['match'])) {
            return abort(404);
        }
        $match = $json['match'];
        $channels = $json['live']['channels'];
        $this->_saveAppData($json,1, $id);
        $json['live']['channels'] = $channels;
        if ($match['sport'] == 1 && array_key_exists($match['lid'],Match::path_league_football_arrays)){
            $zhuangti = Match::path_league_football_arrays[$match['lid']];
            $data = \App\Http\Controllers\Controller::SUBJECT_NAME_IDS[$zhuangti];
            $data['name_en'] = $zhuangti;
            $json['zhuanti'] = $data;
        }
        else if($match['sport'] == 2 && array_key_exists($match['lid'],Match::path_league_basketball_arrays)){
            $zhuangti = Match::path_league_basketball_arrays[$match['lid']];
            $data = \App\Http\Controllers\Controller::SUBJECT_NAME_IDS[$zhuangti];
            $data['name_en'] = $zhuangti;
            $json['zhuanti'] = $data;
        }
        return view('pc.live.video', $json);
    }

    /**
     * 篮球直播终端
     * @param Request $request
     * @param $id
     * @param bool $immediate 是否即时数据
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function basketDetail(Request $request, $id, $immediate = false) {
        $con = new AikanQController();
        $json = $con->basketDetailJsonData($id, false);
        if (!isset($json)) {
            return abort(404);
        }
        $json['articles'] = PcArticle::randArticles(12);
        return $this->basketDetailHtml($json, $id);
    }

    public function basketDetailHtml($json, $id) {
        if (!isset($json['match'])) {
            return abort(404);
        }
        $match = $json['match'];

        $this->_saveAppData($json,2,$id);
        if ($match['sport'] == 1 && array_key_exists($match['lid'],Match::path_league_football_arrays)){
            $zhuangti = Match::path_league_football_arrays[$match['lid']];
            $data = \App\Http\Controllers\Controller::SUBJECT_NAME_IDS[$zhuangti];
            $data['name_en'] = $zhuangti;
            $json['zhuanti'] = $data;
        }
        else if($match['sport'] == 2 && array_key_exists($match['lid'],Match::path_league_basketball_arrays)){
            $zhuangti = Match::path_league_basketball_arrays[$match['lid']];
            $data = \App\Http\Controllers\Controller::SUBJECT_NAME_IDS[$zhuangti];
            $data['name_en'] = $zhuangti;
            $json['zhuanti'] = $data;
        }
        return view('pc.live.video', $json);
    }

    /**
     * 自建直播终端
     * @param Request $request
     * @param $id
     * @param $immediate
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function otherDetail(Request $request, $id, $immediate = false) {
        $con = new AikanQController();
        $json = $con->otherDetailJsonData($id, false);
        $json['articles'] = PcArticle::randArticles(12);
        return $this->otherDetailHtml($json, $id);
    }

    /**
     * 自建终端html
     * @param $json
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function otherDetailHtml($json, $id) {
        if (isset($json['match'])) {
            $match = $json['match'];
            $time = date('m月d H:i', strtotime($match['time']));
            $json['title'] = $match['lname'] . ' ' . $match['hname'] . (!empty($match['aname'] ? (' VS ' . $match['aname']) : '')) . ' ' . $time . "-爱看球直播";
            $json['keywords'] = '爱看球直播,' . $match['lname'] . '直播,' . $match['hname'] . '直播,' . $match['aname'] . '直播,高清直播';
            $json['description'] = '爱看球正在为直播 ' . date('m月d H:i', strtotime($match['time'])) . ' ' . $match['lname'] . ' ' . $match['hname'] . (!empty($match['aname'] ? (' VS ' . $match['aname']) : '')) . "，JRS低调看直播就来爱看球直播。";
        } else {
            return abort(404);
        }
        $this->_saveAppData($json,3,$id);
        if ($match['sport'] == 1 && array_key_exists($match['lid'],Match::path_league_football_arrays)){
            $zhuangti = Match::path_league_football_arrays[$match['lid']];
            $data = \App\Http\Controllers\Controller::SUBJECT_NAME_IDS[$zhuangti];
            $data['name_en'] = $zhuangti;
            $json['zhuanti'] = $data;
        }
        else if($match['sport'] == 2 && array_key_exists($match['lid'],Match::path_league_basketball_arrays)){
            $zhuangti = Match::path_league_basketball_arrays[$match['lid']];
            $data = \App\Http\Controllers\Controller::SUBJECT_NAME_IDS[$zhuangti];
            $data['name_en'] = $zhuangti;
            $json['zhuanti'] = $data;
        }
        return view('pc.live.video', $json);
    }

    /**
     * 播放器channel
     * @param Request $request
     * @param $mid
     * @param $sport
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function matchPlayerChannel(Request $request, $mid = '',$sport = ''){
        $mid = $request->input('mid', $mid);
        $sport = $request->input('sport', $sport);
        $akqCon = new AikanQController();

        $ch = curl_init();
        if ($sport == 3) {
            $json = $akqCon->otherDetailJsonData($mid);
        } else if ($sport == 2) {
            $json = $akqCon->basketDetailJsonData($mid);
        } else {
            $json = $akqCon->detailJsonData($mid, false);
        }

        if (isset($json) && isset($json['live']) && isset($json['live']['channels'])){
            $channels = $json['live']['channels'];
        } else{
            $channels = array();
        }

        $ch = curl_init();
        if ($sport == 3) {
            $json = $akqCon->otherDetailJsonData($mid, true);
        } else if ($sport == 2) {
            $json = $akqCon->basketDetailJsonData($mid, true);
        } else {
            $json = $akqCon->detailJsonData($mid, true);
        }
        if (isset($json) && isset($json['live']) && isset($json['live']['channels'])){
            $mchannels = $json['live']['channels'];
        } else{
            $mchannels = array();
        }
        return view('pc.live.match_channel',array('mchannels'=>$mchannels,'channels'=>$channels,'cdn'=>env('CDN_URL'),'host'=>'www.aikq.cc'));
    }

    /**
     * 播放器channel
     * @param Request $request
     * @param $mid
     * @param  $sport
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function dbMatchPlayerChannel(Request $request, $mid, $sport) {
        $json = AikanQController::matchDetailArray($mid, $sport, false);
        if (isset($json) && isset($json['live']) && isset($json['live']['channels'])){
            $channels = $json['live']['channels'];
        } else{
            $channels = array();
        }

        $json = AikanQController::matchDetailArray($mid, $sport, true);
        if (isset($json) && isset($json['live']) && isset($json['live']['channels'])){
            $mChannels = $json['live']['channels'];
        }
        else{
            $mChannels = array();
        }
        return view('pc.live.match_channel',array('mchannels'=>$mChannels,'channels'=>$channels,'cdn'=>env('CDN_URL'),'host'=>'www.aikq.cc'));
    }

    /**
     * 播放器
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function matchPlayer(Request $request){
        return view('pc.live.match_player');
    }

    /**
     * 播放器
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function player(Request $request){
        return view('pc.live.player',array('cdn'=>env('CDN_URL'),'host'=>'www.aikq.cc'));
    }

    public function share(Request $request){
        return view('pc.live.player');
    }


    /**
     * 获取playurl根据比赛id
     * @param Request $request
     * @param $mid
     * @return mixed
     */
    public function getLiveUrlMatch(Request $request,$mid){
        $ch = curl_init();
        $isMobile = \App\Http\Controllers\Controller::isMobile($request)?1:0;
        $sport = $request->input('sport',1);

        $akqCon = new AikanQController();
        $data = $akqCon->getLiveUrlMatch($request, $mid, $isMobile, $sport)->getData();
        $server_output = json_encode($data);
        return $server_output;
    }

    public function getLiveUrlMatch2(Request $request,$mid,$sport,$isMobile){
        $akqCon = new AikanQController();
        $data = $akqCon->getLiveUrlMatch($request, $mid, $isMobile, $sport)->getData();
        $server_output = json_encode($data);
        return $server_output;
    }

    public function getLiveUrlMatchFromDb(Request $request, $mid, $sport, $isMobile) {
        $aikCon = new AikanQController();
        $data = $aikCon->getLiveUrlMatch($request, $mid, $isMobile, $sport)->getData();
        return json_encode($data);
    }

    public function getLiveUrlMatchM(Request $request,$mid,$sport){
        $akqCon = new AikanQController();
        $data = $akqCon->getLiveUrlMatch($request, $mid, true, $sport)->getData();
        $server_output = json_encode($data);
        return $server_output;
    }

    public function getLiveUrlMatchPC(Request $request,$mid,$sport){
        $akqCon = new AikanQController();
        $data = $akqCon->getLiveUrlMatch($request, $mid, false, $sport)->getData();
        $server_output = json_encode($data);
        return $server_output;
    }

    /**
     * 获取无插件playurl
     * @param Request $request
     * @param $mid
     * @return mixed
     */
    public function getLiveUrl(Request $request,$mid){
        $code = isset($_COOKIE[self::LIVE_HD_CODE_KEY]) ? $_COOKIE[self::LIVE_HD_CODE_KEY] : '';//$request->cookie(self::LIVE_HD_CODE_KEY);//cookie的验证码//$code = $request->cookie(self::LIVE_HD_CODE_KEY);
        $isMobile = \App\Http\Controllers\Controller::isMobile($request)?1:0;

        $akqCon = new AikanQController();
        $data = $akqCon->getLiveUrl($request, $mid, $isMobile)->getData();
        $server_output = json_encode($data);
        return $server_output;
    }


    /**
     * 更新视频终端。
     * @param Request $request
     * @param $mid
     * @param $sport
     * @param $ch_id
     */
    public function staticLiveDetailById(Request $request, $mid, $sport, $ch_id = '') {
        $ch_id = empty($ch_id) ? $request->input('ch_id') : $ch_id;
        try {
            $path = CommonTool::getLiveDetailStaticPath($mid, $sport);
            $pcPath = "/www" . $path;
            $mPath = "/m" . $path;
            $mipPath = "/mip" . $path;

            $mCon = new \App\Http\Controllers\Mobile\Live\LiveController();
            $mipCon = new \App\Http\Controllers\Mip\Live\LiveController();
            if ($sport == 1) {
                $html = $this->footballDetail($request, $mid, true);
                if (!empty($html)) {
                    Storage::disk("public")->put($pcPath, $html);
                }

                $mHtml = $mCon->footballDetail($request, $mid, true);
                if (!empty($mHtml)) {
                    Storage::disk("public")->put($mPath, $mHtml);
                }
                $mipHtml = $mipCon->footballDetail($request, $mid, true);
                if (!empty($mipHtml)) {
                    Storage::disk("public")->put($mipPath, $mipHtml);
                }
            } else if($sport == 2){
                $html = $this->basketDetail($request, $mid, true);
                if (!empty($html)) {
                    Storage::disk("public")->put($pcPath, $html);
                }

                $mHtml = $mCon->basketballDetail($request, $mid, true);
                if (!empty($mHtml)) {
                    Storage::disk("public")->put($mPath, $mHtml);
                }
                $mipHtml = $mipCon->basketballDetail($request, $mid, true);
                if (!empty($mipHtml)) {
                    Storage::disk("public")->put($mipPath, $mipHtml);
                }
            } else if ($sport == 3) {
                $html = $this->otherDetail($request, $mid, true);
                if (!empty($html)) {
                    Storage::disk("public")->put($pcPath, $html);
                }

                $mHtml = $mCon->otherDetail($request, $mid);
                if (!empty($mHtml)) {
                    Storage::disk("public")->put($mPath, $mHtml);
                }
                $mipHtml = $mipCon->otherDetail($request, $mid);
                if (!empty($mipHtml)) {
                    Storage::disk("public")->put($mipPath, $mipHtml);
                }
            }
            //每一个比赛的player页面生成
            $this->staticLiveDetailPlayerAndJson($request, $mid, $sport);
            $this->staticLiveChannelsJson($request, $mid, $sport);
            if (is_numeric($ch_id)) {
                $this->staticLiveUrl($request, $ch_id, true);
            }
        } catch (\Exception $exception) {
            echo $exception->getMessage();
            Log::error($exception);
        }
    }

    /**
     * 直播终端静态化
     * @param $request
     * @param $mid
     * @param $sport
     */
    protected function staticLiveDetailPlayerAndJson($request, $mid, $sport) {
        //每一个比赛的player页面生成
        $phtml = $this->matchPlayerChannel($request, $mid, $sport);
        if (!empty($phtml)) {
            Storage::disk("public")->put("/www/live/spPlayer/player-" . $mid . '-' . $sport . ".html", $phtml);
        }
        //match.json
        $mjson = $this->getLiveUrlMatch2(new Request(),$mid,$sport,true);
        if (!empty($mjson)) {
            Storage::disk("public")->put("/www/match/live/url/match/m/" . $mid . "_" . $sport .".json", $mjson);
        }
        $pjson = $this->getLiveUrlMatch2(new Request(),$mid,$sport,false);
        if (!empty($mjson)) {
            Storage::disk("public")->put("/www/match/live/url/match/pc/" . $mid . "_" . $sport .".json", $pjson);
        }
    }

    /**
     * 静态化给cms的线路接口
     * @param $request
     * @param $mid
     * @param $sport
     */
    protected function staticLiveChannelsJson($request, $mid, $sport) {
        $cmsCon = new CmsController();
        $data = $cmsCon->getChannels($request, $mid, $sport)->getData();
        $path = "www/json/cms/channels/$mid/$sport.json";
        Storage::disk('public')->put($path, json_encode($data));
    }

    /**
     * 静态化直播线路的json
     * @param Request $request
     * @param $id
     * @param $has_mobile
     * @param $sport
     */
    public function staticLiveUrl(Request $request, $id, $has_mobile = false, $sport = null) {
        try {
            $player = $this->player($request);
            $sport = !isset($sport) ? $request->input('sport',1) : $sport;
            $has_mobile = $has_mobile || $request->input('has_mobile') == 1;
            $aiCon = new AikanQController();
            $jsonStr = $aiCon->getLiveUrl($request, $id)->getData();
            $pc_json = json_encode($jsonStr);
            if (!empty($pc_json)) {
                Storage::disk("public")->put("/www/match/live/url/channel/". $id . '.json', $pc_json);
                //每一个channel的player页面生成
                $json = json_decode($pc_json,true);
                if (strlen($player) > 0 && $json && array_key_exists('code',$json) && $json['code'] == 0) {
                    Storage::disk("public")->put("/www/live/player/player-" . $id . '-' . $json['type'] . ".html", $player);
                }

                //保存app
                $key = env('APP_DES_KEY');
                $iv=env('APP_DES_IV');
                $appData = $json;

                if (isset($appData['playurl']) && strlen($appData['playurl']) > 5) {
                    $appData['playurl'] = openssl_encrypt($appData['playurl'], "DES", $key, 0, $iv);
                }
                $appData = json_encode($appData);
                Storage::disk("public")->put("/app/v101/channels/" . $id . '.json', $appData);
                Storage::disk("public")->put("/app/v110/channels/" . $id . '.json', $appData);
            }
            if ($has_mobile) {
                $mobile_json = $pc_json;
                if (!empty($mobile_json)) {
                    Storage::disk("public")->put("/www/match/live/url/channel/mobile/". $id . '.json', $mobile_json);
                }
            } else {
                if (!empty($pc_json)) {
                    Storage::disk("public")->put("/www/match/live/url/channel/mobile/". $id . '.json', $pc_json);
                }
            }
        } catch (\Exception $e) {
            dump($e);
            Log::error($e);
        }
    }

    /**
     * 静态化直播线路的json
     * @param Request $request
     * @param $id
     * @param $has_mobile
     * @param $sport
     */
    public function staticDBLiveUrl(Request $request, $id, $has_mobile = false, $sport = null) {
        try {
            $player = $this->player($request);
            $sport = !isset($sport) ? $request->input('sport',1) : $sport;
            $has_mobile = $has_mobile || $request->input('has_mobile') == 1;
            $aiCon = new AikanQController();
            $jsonStr = $aiCon->getLiveUrl($request, $id, $has_mobile)->getData();
            $pc_json = json_encode($jsonStr);
            if (!empty($pc_json)) {
                Storage::disk("public")->put("/match/live/url/channel/". $id . '.json', $pc_json);
                //每一个channel的player页面生成
                $json = json_decode($pc_json,true);
                if (strlen($player) > 0 && $json && array_key_exists('code',$json) && $json['code'] == 0) {
                    Storage::disk("public")->put("/live/player/player-" . $id . '-' . $json['type'] . ".html", $player);
                }

                //保存app
                $key = env('APP_DES_KEY');
                $iv=env('APP_DES_IV');
                $appData = $json;

                if (isset($appData['playurl']) && strlen($appData['playurl']) > 5) {
                    $appData['playurl'] = openssl_encrypt($appData['playurl'], "DES", $key, 0, $iv);
                }
                $appData = json_encode($appData);
                Storage::disk("public")->put("/app/v101/channels/" . $id . '.json', $appData);
                Storage::disk("public")->put("/app/v110/channels/" . $id . '.json', $appData);
            }
            if ($has_mobile) {
                $mobile_json = $pc_json;
                if (!empty($mobile_json)) {
                    Storage::disk("public")->put("/match/live/url/channel/mobile/". $id . '.json', $mobile_json);
                }
            } else {
                if (!empty($pc_json)) {
                    Storage::disk("public")->put("/match/live/url/channel/mobile/". $id . '.json', $pc_json);
                }
            }
        } catch (\Exception $e) {
            dump($e);
        }
    }


    /**
     * 输入验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validCode(Request $request) {
        $code = $request->input('code');
        if (empty($code)) {
            return response()->json(['code'=>401, 'msg'=>'请输入验证码']);
        }
        $r_code = Redis::get(self::LIVE_HD_CODE_KEY);
        $code = strtoupper($code);
        if ($code != $r_code) {
            return response()->json(['code'=>403, 'msg'=>'验证码错误']);
        }
        //$c = cookie(self::LIVE_HD_CODE_KEY, $code, strtotime('+10 years'), '/');
        setcookie(self::LIVE_HD_CODE_KEY, $code, strtotime('+10 years'), '/');
        return response()->json(['code'=>200, 'msg'=>'验证码正确']);//->withCookie($c);
    }

    /**
     * 接收验证码
     * @param Request $request
     * @param $code
     */
    public function recCode(Request $request, $code) {
        Redis::set(self::LIVE_HD_CODE_KEY, $code);
        try {
            $json = Storage::get('public/static/m/dd_image/images.json');
            $json = json_decode($json, true);
        } catch (\Exception $e) {
        }
        if (isset($json)) {
            $json['code'] = $code;
        } else {
            $json = ['code'=>$code];
        }
        Storage::disk('public')->put('/static/m/dd_image/images.json', json_encode($json));
    }

    /**
     * playurl
     * @param Request $request
     * @param $ch_id
     * @return mixed
     */
    public function getHLiveUrl(Request $request, $ch_id){
        $code = isset($_COOKIE[self::LIVE_HD_CODE_KEY]) ? $_COOKIE[self::LIVE_HD_CODE_KEY] : '';//$request->cookie(self::LIVE_HD_CODE_KEY);//cookie的验证码
        $r_code = Redis::get(self::LIVE_HD_CODE_KEY);//服务器的高清验证码
        try {
            //获取缓存文件 开始
            $server_output = Storage::get('/public/match/live/url/channel/' . $ch_id . '.json');//文件缓存
            //获取缓存文件 结束
        } catch (\Exception $exception) {
            $isMobile = \App\Http\Controllers\Controller::isMobile($request) ? 1 : 0;
            $akqCon = new AikanQController();
            $server_output = $akqCon->getLiveUrl($request, $ch_id, $isMobile)->getData();
            $server_output = json_encode($server_output);
        }
        $json = json_decode($server_output, true);
        if (is_null($json)) {
            return response()->json(['code'=>-1]);
        }
        if (isset($json['h_playurl'])) {
            if (!empty($code) && !empty($r_code) && strtoupper($code) == $r_code) {
                $json['playurl'] = $json['h_playurl'];
                $json['hd'] = true;
            }
            unset($json['h_playurl']);
        }
        return \response()->json($json);
    }

    /**
     * 获取广告图片
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVideoAdImage(Request $request) {
        $patch = '/public/static/m/dd_image';
        $default_img = '/img/pc/demo.jpg';
        $l_file = $this->getStorageFirstFile($patch.'/l');
        $d_file = $this->getStorageFirstFile($patch.'/d');
        $z_file = $this->getStorageFirstFile($patch.'/z');
        $w_file = $this->getStorageFirstFile($patch.'/w');

        $l_file = empty($l_file) ? $default_img : str_replace('public/static', '', $l_file);
        $d_file = empty($d_file) ? $default_img : str_replace('public/static', '', $d_file);
        $z_file = empty($z_file) ? $default_img : str_replace('public/static', '', $z_file);
        $w_file = empty($w_file) ? $default_img : str_replace('public/static', '', $w_file);

        return \response()->json(['l'=>$l_file, 'd'=>$d_file, 'z'=>$z_file, 'w'=>$w_file]);
    }

    /**
     * 静态化广告文件
     * @param $type
     * @param $cd_name
     * @param $cd_text
     */
    protected function staticAdImages($type, $cd_name, $cd_text) {
        $patch = '/public/static/m/dd_image';
        $default_img = '/img/pc/demo.jpg';
        $l_file = $this->getStorageFirstFile($patch.'/l');
        $d_file = $this->getStorageFirstFile($patch.'/d');
        $z_file = $this->getStorageFirstFile($patch.'/z');
        $w_file = $this->getStorageFirstFile($patch.'/w');
        $cd_file = $this->getStorageFirstFile($patch.'/cd');

        $l_file = empty($l_file) ? $default_img : str_replace('public/static', '', $l_file);
        $d_file = empty($d_file) ? $default_img : str_replace('public/static', '', $d_file);
        $z_file = empty($z_file) ? $default_img : str_replace('public/static', '', $z_file);
        $w_file = empty($w_file) ? $default_img : str_replace('public/static', '', $w_file);
        $cd_file = empty($cd_file) ? '/img/pc/image_qr_868.jpg' : str_replace('public/static', '', $cd_file);

        $r_code = Redis::get(self::LIVE_HD_CODE_KEY);
        $json = ['l'=>$l_file, 'd'=>$d_file, 'z'=>$z_file, 'w'=>$w_file, 'code'=>$r_code, 'cd'=>$cd_file];
        if ($type == 5) {
            $json['cd_name'] = $cd_name;
            $json['cd_text'] = $cd_text;
        }
        Storage::disk('public')->put('/static/m/dd_image/images.json', json_encode($json));
    }

    /**
     * @param Request $request
     * @return string|void
     */
    public function setActive(Request $request) {
        $type = $request->input('type');
        if ($type == 99) {
            //清空活动内容
            Storage::disk('public')->put('/static/m/dd_image/active.json', json_encode([]));
            return;
        }
        $active = $request->input('active');//json内容
        if (!empty($active)) {
            $active = urldecode($active);
        }
        $active = json_decode($active, true);
        //dump($active);
        if (!isset($active) || empty($active['code']) || empty($active['txt']) ) {
            return "参数错误";
        }
        $save_patch = '/static/m/dd_image/active/';
        $patch = $active['code'];

        $this->delStorageFiles('/public' . $save_patch);//删除图片

        //保存图片 开始
//        $start = substr($patch, 0, 1);
//        if($start == '/') {
//            $patch = substr($patch, 1);
//        }
        $url = $patch;
        $ch = curl_init();

        $timeout = 10;
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, $timeout);
        $img = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code >= 400) {
            echo "获取链接内容失败";
            return;
        }

        $list = explode("/", $url);
        $ext = $list[count($list) - 1];
        $list = explode('?', $ext);
        $fileName = $list[0];
        $file_patch = $save_patch . $fileName;
        Storage::disk('public')->put($file_patch, $img);
        //保存图片 结束

        $file_patch = str_replace('/static', '', $file_patch);
        $txt = $active['txt'];
        $pattern = '[\n+\r*|\r+\n*]';
        $txt = preg_replace($pattern, "\n", $txt);
        $active['txt'] = $txt;
        $active['code'] = $file_patch;
        Storage::disk('public')->put('/static/m/dd_image/active.json', json_encode($active));
    }

    /**
     * 获取文件夹下的第一个文件
     * @param $patch
     * @return mixed|string
     */
    protected function getStorageFirstFile($patch) {
        $files = Storage::files($patch);
        if (is_array($files) && count($files) > 0) {
            return $files[0];
        }
        return '';
    }

    /**
     * 删除文件夹下面的所有文件
     * @param $patch
     */
    protected function delStorageFiles($patch) {
        $files = Storage::files($patch);
        if (is_array($files)) {
            foreach ($files as $file) {
                Storage::delete($file);
            }
        }
    }

    /* app用 */
    private function _saveAppData($json,$sport,$mid){
        $key = env('APP_DES_KEY');
        $iv=env('APP_DES_IV');
        $appData = $json;
        $channels = array();
        if ($json['live'] && $json['live']['channels']) {
            foreach ($json['live']['channels'] as $channel) {
                if (isset($channel['link']) && !stristr($channel['link'],'leqiuba.cc'))
                {
                    //乐球吧不要
                    $channel['link'] = openssl_encrypt($channel['link'], "DES", $key, 0, $iv);
                    $channels[] = $channel;
                }
            }
            $appData['live']['channels'] = $channels;
        }
        $appData = json_encode($appData);
        Storage::disk("public")->put("/app/v101/lives/" . $sport . '/' . $mid . '.json', $appData);
        Storage::disk("public")->put("/app/v110/lives/" . $sport . '/' . $mid . '.json', $appData);
    }

    public function appLiveDetail(Request $request,$sport,$mid){
        $akqCon = new AikanQController();
        $ch = curl_init();
        if ($sport == 1) {
            $json = $akqCon->detailJsonData($mid, false);
        }
        else if ($sport == 2) {
            $json = $akqCon->basketDetailJsonData($mid, false);
        }
        else if ($sport == 3) {
            $json = $akqCon->otherDetailJsonData($mid, false);
        }
        if (is_null($json)){
            return null;
        }
        $this->_saveAppData($json,$sport,$mid);
        return json_encode($json);
    }
}