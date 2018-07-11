<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/1
 * Time: 18:37
 */

namespace App\Http\Controllers\Admin\Match;



use App\Models\AdConf;
use App\Models\Match\BasketMatch;
use App\Models\Match\League;
use App\Models\Match\Match;
use App\Models\Match\MatchLive;
use App\Models\Match\MatchLiveChannel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MatchController extends Controller
{
    const hot_league_array = ["英超","英冠","英联杯","英足总杯","意甲","意杯","西甲","西杯","德甲","德国杯","德乙","法甲","法乙","法国杯","法联杯","荷兰杯","葡超","葡杯","葡联杯","苏超","苏总杯","荷甲","荷乙","荷兰杯","比甲","比利时杯","瑞典超","瑞典杯","挪超","挪威杯","丹麦超","丹麦杯","奥甲","奥地利杯","瑞士超","瑞士杯","爱超","北爱超","爱足杯","俄超","俄杯","波兰超","乌克超","捷甲","希腊超","罗甲","冰岛超","冰岛杯","威超","匈甲","土超","克亚甲","阿甲","巴西甲","美职业","智利甲","墨西联","墨西哥杯","加拿超","中超","中甲","中协杯","日职联","日联杯","日皇杯","韩K联","韩足总","澳洲甲","澳足总","世界杯"];
    const hot_league_id_array = [1,2,3,4,5,6,7,8,9,10,11,12,15,16,17,18,19,20,21,22,23,24,25,26,27,29,31,32,38,39,42,43,45,46,47,49,52,54,55,56,60,62,63,64,66,69,72,74,82,87,94,95,96,97,100,110,117,119,120,124,142,175,187,262,289,296,321,768,57];

    /**
     * 篮球比赛列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function todayBasketMatch(Request $request) {
        $t_name = $request->input("t_name");//球队名称
        $l_name = $request->input("l_name");//赛事名称
        $has_live = $request->input('has_live');//是否有直播链接
        $status = $request->input('status');//比赛状态
        $type = $request->input('type');//比赛类型 1：竞彩、2：精简

        $withSelect = true;
        $isMain = false;

        $startDate = date("Y-m-d", strtotime('-1 days'));
        $endDate = date('Y-m-d H:i:s', strtotime('3 days'));

        $sport = MatchLive::kSportBasketball;
        $match_table = 'basket_matches';

        if ($has_live == 1) {//有直播链接
            $query = MatchLive::query();
            $query->join('basket_matches', function ($join) use ($sport, $match_table) {
                $join->on('match_lives.match_id', '=', $match_table . '.id');
                $join->where('match_lives.sport', $sport);
            });
        } else {
            $query = BasketMatch::query();
            $query->leftJoin('match_lives', function ($join) use ($sport, $match_table) {
                $join->on('match_lives.match_id', '=', $match_table . '.id');
                $join->where('match_lives.sport', $sport);
            });

            if ($has_live == 2) {
                //无直播链接
                $query->whereNull('match_lives.id');
            }
            $query->where("time", ">=", $startDate)->where("time", "<", $endDate);
        }

        if ($withSelect) {
            $query->select($match_table . ".*", $match_table .".id as mid", $match_table . ".win_lname as league_name");
        }
        $query->addSelect(['match_lives.sport', 'match_lives.id as live_id']);

        if ($isMain) {
            $query->orderBy('leagues.main', 'desc');
        }
        if (!empty($t_name)) {
            $query->where(function ($orQuery) use ($t_name, $match_table) {
                $orQuery->where($match_table . '.hname', 'like', "%$t_name%");
                $orQuery->orWhere($match_table . '.aname', 'like', "%$t_name%");
            });
        }
        if ($status == 1) {//未开始
            $query->where($match_table . '.status', 0);
        } elseif ($status == 2) {//进行中
            $query->where(function ($orQuery) use ($match_table) {
                $orQuery->where($match_table . '.status', 1);//第一节
                $orQuery->orWhere($match_table.'.status', 2);//第二节
                $orQuery->orWhere($match_table.'.status', 3);//第三节
                $orQuery->orWhere($match_table.'.status', 4);//第四节
                $orQuery->orWhere($match_table.'.status', 5);//加时 第一节
                $orQuery->orWhere($match_table.'.status', 6);//加时 第二节
                $orQuery->orWhere($match_table.'.status', 7);//加时 第三节
                $orQuery->orWhere($match_table.'.status', 8);//加时 第四节
                $orQuery->orWhere($match_table.'.status', 50);//中场休息
            });
        } elseif ($status == 3) {
            $query->where($match_table.'.status', -1);//已结束
        }
        if ($type == 1) {
            $query->whereNotNull($match_table . '.betting_num');
        } /*elseif ($type == 2) {
            $query->where('matches.genre', '&', Match::k_genre_yiji);
        }*/
        if (!empty($l_name)) {
            $query->where($match_table . '.win_lname', 'like', '%' . $l_name . '%');
        }
        $query->orderBy($match_table. '.status', 'desc');
        $query->orderBy($match_table . '.time', 'asc');
        $query->orderBy($match_table . '.id', 'desc');

        $matches = $query->paginate(20);
        $matches->appends($request->all());
        $rest = ['matches'=>$matches, 'sport'=>MatchLive::kSportBasketball, 'types'=>MatchLiveChannel::kTypeArrayCn];
        $rest['private_arr'] = MatchLive::BasketballPrivateArray;
        $rest['ch_code'] = AdConf::getValue(AdConf::CMS_SHD_CHANNEL_CODE_KEY);
        return view('admin.match.live_matches', $rest);
    }

    /**
     * 今天的比赛，设置直播链接
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function todayMatch(Request $request) {
        $t_name = $request->input("t_name");//球队名称
        $l_name = $request->input("l_name");//赛事名称
        $has_live = $request->input('has_live');//是否有直播链接
        $status = $request->input('status');//比赛状态
        $type = $request->input('type');//比赛类型 1：竞彩、2：精简

        if (!empty($l_name)) {
            $lid_array = $this->getLeagueIdByName($l_name);
        }

        $withSelect = true;
        $isMain = false;

        $startDate = date("Y-m-d", strtotime('-1 days'));
        $endDate = date('Y-m-d H:i:s', strtotime('3 days'));

        if ($has_live == 1) {//有直播链接
            $query = MatchLive::query();
            $query->join('matches', function ($join) {
                $join->on('match_lives.match_id', '=', 'matches.id');
                $join->where('match_lives.sport', MatchLive::kSportFootball);
            });
        } else {
            $query = Match::query();
            $query->leftJoin('match_lives', function ($join) {
                $join->on('match_lives.match_id', '=', 'matches.id');
                $join->where('match_lives.sport', MatchLive::kSportFootball);
            });

            if ($has_live == 2) {
                //无直播链接
                $query->whereNull('match_lives.id');
            }
            $query->where("time", ">=", $startDate)->where("time", "<", $endDate);
        }

        $query->leftJoin("leagues", "matches.lid", "leagues.id");
        if ($withSelect) {
            $query->select("matches.*", "matches.id as mid", "leagues.name as league_name");
        }
        $query->addSelect(['match_lives.sport', 'match_lives.id as live_id']);

        if ($isMain) {
            $query->orderBy('leagues.main', 'desc');
        }
        if (!empty($t_name)) {
            $query->where(function ($orQuery) use ($t_name) {
                $orQuery->where('matches.hname', 'like', "%$t_name%");
                $orQuery->orWhere('matches.aname', 'like', "%$t_name%");
            });
        }
        if ($status == 1) {//未开始
            $query->where('matches.status', 0);
        } elseif ($status == 2) {//进行中
            $query->where(function ($orQuery) {
                $orQuery->where('status', 1);//上半场
                $orQuery->orWhere('status', 2);//中场
                $orQuery->orWhere('status', 3);//下半场
                $orQuery->orWhere('status', 4);//加时
            });
        } elseif ($status == 3) {
            $query->where('status', -1);
        }
        if ($type == 1) {
            $query->whereNotNull('matches.betting_num');
        } elseif ($type == 2) {
            $query->where('matches.genre', '&', Match::k_genre_yiji);
        }
        if (isset($lid_array) && count($lid_array) > 0) {
            $query->whereIn('lid', $lid_array);
        }
        $query->orderBy('status', 'desc');
        $query->orderBy('time', 'asc');
        $query->orderBy('id', 'desc');

        $matches = $query->paginate(20);
        $matches->appends($request->all());
        $rest = ['matches'=>$matches, 'sport'=>MatchLive::kSportFootball, 'types'=>MatchLiveChannel::kTypeArrayCn];
        $rest['private_arr'] = MatchLive::FootballPrivateArray;
        $rest['ch_code'] = AdConf::getValue(AdConf::CMS_SHD_CHANNEL_CODE_KEY);
        return view('admin.match.live_matches', $rest);
    }

    /**
     * 保存直播线路
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveChannel(Request $request) {
        $channel_id = $request->input('channel_id');//线路id
        $match_id = $request->input('match_id');//比赛id
        $sport = $request->input('sport');//竞技类型

        $type = $request->input('type');//线路类型
        $platform = $request->input('platform');//线路显示平台
        $isPrivate = $request->input('isPrivate');//是否有版权，2、有版权，2、无版权。
        $show = $request->input('show');//是否显示线路
        $od = $request->input('od');//线路排序
        $player = $request->input('player');//线路播放方式
        $content = $request->input('content');//线路内容
        $h_content = $request->input('h_content');//高清线路内容 当type = 9 时 即：高清验证类型，需要判断该链接是否为空
        $name = $request->input('name');//线路名称
        $use = $request->input('use');//使用这个线路的网站，1、通用，2、爱、3、黑土、4、310.
        $impt = $request->input('impt');//是否重点线路，1：普通，2：重点线路
        $ad = $request->input('ad', 1);//

        //判断参数 开始
        if (!in_array($type, MatchLiveChannel::kTypeArray)) {
            return response()->json(['code'=>401, 'msg'=>'线路类型错误。']);
        }
        if (!in_array($platform, [MatchLiveChannel::kPlatformAll, MatchLiveChannel::kPlatformPC, MatchLiveChannel::kPlatformWAP])) {
            return response()->json(['code'=>401, 'msg'=>'平台参数错误。']);
        }
        if (!in_array($isPrivate, [MatchLiveChannel::kIsPrivate, MatchLiveChannel::kIsNotPrivate])) {
            return response()->json(['code'=>401, 'msg'=>'是否有版权参数错误。']);
        }
        if (!in_array($show, [MatchLiveChannel::kShow, MatchLiveChannel::kHide])) {
            return response()->json(['code'=>401, 'msg'=>'显示参数错误。']);
        }
        if (!empty($od) && !is_numeric($od)) {
            return response()->json(['code'=>401, 'msg'=>'排序必须为数字。']);
        }
        if (!in_array($player, MatchLiveChannel::kPlayerArray)) {
            return response()->json(['code'=>401, 'msg'=>'播放参数错误。']);
        }
        if (!in_array($ad, [MatchLiveChannel::kHasAd, MatchLiveChannel::kNoAd])) {
            return response()->json(['code'=>401, 'msg'=>'是否有广告参数错误。']);
        }
        if (empty($content)) {
            return response()->json(['code'=>401, 'msg'=>'必须填写线路内容。']);
        }
        if ($type == MatchLiveChannel::kTypeCode && empty($h_content)) {
            return response()->json(['code'=>401, 'msg'=>'必须填写高清线路内容。']);
        }
        if (!in_array($use, [1, 2, 3, 4])) {
            return response()->json(['code'=>401, 'msg'=>'观看网站参数错误。']);
        }
        if (!in_array($impt, [1, 2])) {
            return response()->json(['code'=>401, 'msg'=>'是否终端线路参数错误。']);
        }
        if (is_numeric($channel_id)) {
            $channel = MatchLiveChannel::query()->find($channel_id);
            if (!isset($channel)) {
                return response()->json(['code'=>403, 'msg'=>'线路不存在。']);
            }
        } else {//新建的线路
            if (!is_numeric($match_id)) {
                return response()->json(['code'=>401, 'msg'=>'比赛ID不能为空']);
            }
            if (!in_array($sport, [MatchLive::kSportFootball, MatchLive::kSportBasketball, MatchLive::kSportSelfMatch])) {
                return response()->json(['code'=>401, 'msg'=>'竞技类型错误']);
            }
            if ($sport == 1) {//足球
                $match = Match::query()->find($match_id);
            } else {
                $match = BasketMatch::query()->find($match_id);
            }
            if (!isset($match)) {
                return response()->json(['code'=>403, 'msg'=>'比赛不存在。']);
            }
        }
        //判断参数 结束

        if (!isset($channel)) {//新建线路
            $channel = new MatchLiveChannel();
        }
        if ($type != MatchLiveChannel::kTypeCode) {
            $h_content = '';
        }
        $channel->type = $type;
        $channel->platform = $platform;
        $channel->isPrivate = $isPrivate;
        $channel->show = $show;
        $channel->od = $od;
        $channel->name = $name;
        $channel->content = $content;
        $channel->h_content = $h_content;
        $channel->player = $player;
        $channel->auto = MatchLiveChannel::kAutoHand;//手动保存。
        $channel->use = $use;
        $channel->impt = $impt;
        $channel->ad = $ad;

        $exception = DB::transaction(function() use ($channel, $match_id, $sport) {
            if (!isset($channel->id)) {
                $live = MatchLive::query()->where('match_id', $match_id)->where('sport', $sport)->first();
                if (!isset($live)) {//查找是否有 直播
                    $live = new MatchLive();
                    $live->match_id = $match_id;
                    $live->sport = $sport;
                    $live->save();
                }
                $channel->live_id = $live->id;
            }
            $channel->save();
        });

        if (isset($exception)) {
            Log::error($exception);
            return response()->json(['code'=>500, 'msg'=>'保存线路失败']);
        }
        $this->flush310Live($match_id, $sport, $channel->id);
        $this->flushAikqLive($match_id, $sport, $channel->id);
        $this->flushHeiTuLive($match_id, $sport, $channel->id);
        return response()->json(['code'=>200, 'msg'=>'保存线路成功']);
    }

    /**
     * 删除线路
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delChannel(Request $request) {
        $id = $request->input('id');
        if (!is_numeric($id)) {
            return response()->json(['code'=>401, 'msg'=>'参数错误']);
        }
        $channel = MatchLiveChannel::query()->find($id);
        if (!isset($channel)) {
            return response()->json(['code'=>403, 'msg'=>'线路不存在']);
        }
        $matchLive = $channel->matchLive;
        $match_id = $matchLive->match_id;
        $sport = $matchLive->sport;
        $exception = DB::transaction(function () use ($channel, $matchLive) {
            $channel->delete();//删除当前线路
            $channels = MatchLive::liveChannels($matchLive->id);
            if (!isset($channels) || count($channels) == 0) {
                $matchLive->delete();//删除直播
            }
        });
        if (isset($exception)) {
            Log::error($exception);
            return response()->json(['code'=>500, 'msg'=>'删除线路失败']);
        }
        $this->flush310Live($match_id, $sport, $id);
        $this->flushAikqLive($match_id, $sport, $id);
        $this->flushHeiTuLive($match_id, $sport, $id);
        return response()->json(['code'=>200, 'msg'=>'删除线路成功']);
    }

    /**
     * 设置重点比赛
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeLiveImp(Request $request) {
        $mid = $request->input('mid');//比赛ID
        $sport = $request->input('sport');//竞技类型
        $impt = $request->input('impt');//是否重点线路 1：普通，2：重点。

        if (!is_numeric($mid) || !in_array($sport, [1, 2]) || !in_array($impt, [1, 2])) {
            return response()->json(['code'=>401, 'msg'=>'参数错误']);
        }
        $live = MatchLive::query()->where('match_id', $mid)->where('sport', $sport)->first();
        if (!isset($live)) {
            return response()->json(['code'=>401, 'msg'=>'请先设置线路后再设置是否重点比赛。']);
        }
        try {
            $live->impt = $impt;
            $live->save();
        } catch (\Exception $exception) {
            return response()->json(['code'=>500, 'msg'=>'设置失败。']);
        }
        return response()->json(['code'=>200, 'msg'=>'设置成功。']);
    }

    protected function flush310Live($match_id, $sport, $ch_id) {
        $url = 'https://www.lg310.com/live/cache/flush?mid=' . $match_id . '&sport=' . $sport . '&ch_id=' . $ch_id . '&time=' . time();
        //QueryList::getInstance()->get($url)->getHtml();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        $server_output = curl_exec ($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    protected function flushHeiTuLive($match_id, $sport, $ch_id) {
        $param = '?id=' . $match_id . '&sport=' . $sport . '&ch_id=' . $ch_id;
        $url = 'http://www.goodzhibo.com/live/flush_cache/detail' . $param;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        $server_output = curl_exec ($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }


    protected function flushAikqLive($match_id, $sport, $ch_id) {
        $url = 'http://www.aikq.cc/live/cache/match/detail_id/' . $match_id . '/' . $sport . '?ch_id=' . $ch_id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        $server_output = curl_exec ($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    /**
     * 获取热门赛事id
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    protected function hotLeagues() {
        $query = League::query();
        $query->whereIn("name", self::hot_league_array);
        return $query->get();
    }

    protected function getLeagueIdByName($name) {
        $query = League::query();
        $query->where("name", 'like' , "%$name%");
        $leagues = $query->get();
        $l_array = [];
        foreach ($leagues as $league) {
            $l_array[] = $league->id;
        }
        return $l_array;
    }

    /**
     * 排序
     * @param $a
     * @param $b
     * @return int
     */
    private function usortTime($a, $b) {
        if ($a['match']['time'] < $b['match']['time']){
            return 1;
        }
        elseif ($a['match']['time'] > $b['match']['time']){
            return -1;
        }
        else{
            return 0;
        }
    }

    /**
     * 获取当前错误的播放列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function liveErrorList(Request $request){
        $key = 'liaogou_pc_error_url_cid';
        if (Redis::exists($key)){
            $cache = Redis::get($key);
            $cache = json_decode($cache,true);
        }
        else{
            $cache = array();
        }
        $result = array();
        $ids = array();
        foreach ($cache as $key=>$value){
            $ids[] = $key;
        }
        $lives = MatchLiveChannel::query()->whereIn('id',$ids)
            ->get();

        foreach ($lives as $live){
            $match = $live->matchLive->getMatch();
            $live['match'] = $match;
        }

        $lives = $lives->toArray();
        usort($lives, array($this,"usortTime"));

        $result['lives'] = $lives;
        return view('admin.match.live_error',$result);
    }

    /**
     * 清空播放错误url
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function liveErrorDel(Request $request){
        $key = 'liaogou_pc_error_url_cid';
        Redis::del($key);
        return back();
    }

    /**
     * 生成高清验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function randomCode(Request $request) {
        $array = [1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z' ];
        $randomCode = implode('', array_random($array, 4));
        $conf = AdConf::query()->find(AdConf::CMS_SHD_CHANNEL_CODE_KEY);
        if (!isset($conf)) {
            $conf = new AdConf();
            $conf->key = AdConf::CMS_SHD_CHANNEL_CODE_KEY;
        }
        $conf->value = $randomCode;
        $conf->save();
        $this->setCode2AiKq($randomCode);
        return response()->json(['code'=>200, 'random_code'=>$randomCode, 'msg'=>'生成高清验证码成功']);
    }

    protected function setCode2AiKq($code) {
        $url = 'http://www.aikq.cc/live/rec-code/' . $code;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        $server_output = curl_exec ($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

}