@extends('mobile.layout.base')

@section('css')
    <link rel="stylesheet" type="text/css" href="/css/mobile/team.css">
@stop
<?php
    $coach = "";
    if(isset($team['lineup']) && count($team['lineup']) > 0) {
        foreach($team['lineup'] as $lineup) {
            if (str_contains($lineup['position'], "教练")) $coach = $lineup['name'];
        }
    }
?>
@section('banner')
    <div id="Navigation">
        <h1>{{$h1}}</h1>
        <div class="banner"><a class="home" href="/"></a>球队信息</div>
    </div>
@endsection

@section('content')
    <div id="Info">
        <img src="{{$team['icon']}}" onerror='this.src="/img/mobile/icon_teamDefault.png"'>
        <div class="name">{{$team['name']}}</div>
        <div class="more">
            <p><span>现任主教练：</span>{{$coach}}</p>
            <p><span>所在城市：</span>{{$team['city']}}</p>
            <p><span>成立时间：</span>{{strlen($team['establish']) > 0 ? $team['establish'] : "-"}}</p>
            <p><span>外文队名：</span>@if(isset($team['nameEn']) && strlen($team['nameEn']) > 0){{$team['nameEn']}} @else - @endif</p>
            <p><span>球队主场：</span>{{$team['gym']}}</p>
        </div>
    </div>
    <div id="Content">
        <div class="tabbox">
            <button class="on" value="Data">最近比赛</button>
            <button value="Player">球队球员</button>
            <button value="News">相关新闻</button>
            <button value="Record">相关录像</button>
        </div>
        <div id="Data" class="default" style="display: ;">
            <table>
                <thead>
                <tr>
                    <th>赛事</th>
                    <th>时间</th>
                    <th>对阵</th>
                    <th>录像/直播</th>
                </tr>
                </thead>
                <tbody>
                @if(isset($lives) && count($lives) > 0)
                    @foreach($lives as $match)
                        <?php $liveUrl = \App\Http\Controllers\Mobile\UrlCommonTool::matchLiveUrl($match['lid'], $match['sport'], $match['mid']) ?>
                        <tr>
                            <td>{{$match['lname']}}</td>
                            <td><span>{{date('y/m/d', $match['time'])}}</span><br/>{{date('H:i', $match['time'])}}</td>
                            <td>
                                @if(isset($match['hid']) && $match['hid'] != $team['id'])
                                    <a href="{{\App\Http\Controllers\Mobile\UrlCommonTool::getTeamDetailUrl($match['sport'], $match['lid'], $match['hid'])}}">{{$match['hname']}}</a>
                                @else
                                    {{$match['hname']}}
                                @endif
                                @if($match['status'] < 0)
                                    {{$match['hscore']}} - {{$match['ascore']}}
                                @else
                                     vs
                                @endif
                                @if(isset($match['aid']) && $match['aid'] != $team['id'])
                                    <a href="{{\App\Http\Controllers\Mobile\UrlCommonTool::getTeamDetailUrl($match['sport'], $match['lid'], $match['aid'])}}">{{$match['aname']}}</a>
                                @else
                                    {{$match['aname']}}
                                @endif
                            </td>
                            <td>
                                @if($match['status'] >= 0)
                                    @foreach($match['channels'] as $c_index=>$channel)
                                        <a href="{{$liveUrl}}?btn={{$c_index}}">{{$channel['name']}}</a>
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif
                </tbody>
            </table>
        </div>
        <div id="Player" class="default" style="display: none;">
            <table>
                <thead>
                <tr>
                    <th>位置</th>
                    <th>号码</th>
                    <th>姓名</th>
                    @if(isset($league) && $league['sport'] == 2)
                        <th>年薪</th>
                    @else
                        <th>估值</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @if(isset($team['lineup']) && count($team['lineup']) > 0)
                    @foreach($team['lineup'] as $lineup)
                        <tr>
                            <td>{{$lineup['position']}}</td>
                            <td>@if(strlen($lineup['num']) > 0)<p>{{$lineup['num']}}</p> @else -@endif</td>
                            <td>{{$lineup['name']}}</td>
                            <td>{{strlen($lineup['value']) > 0 ? $lineup['value']."万" : "-"}}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
        <div id="News" style="display: none;">
            @if(isset($articles) && count($articles) > 0)
                @foreach($articles as $article)
                    <a href="{{$article['link']}}">
                        <p class="imgbox" style="background: url({{$article['cover']}}) no-repeat center; background-size: cover;"></p>
                        <p class="con">{{$article['title']}}</p>
                    </a>
                @endforeach
            @endif
        </div>
        <div id="Record">
            @if(isset($videos) && count($videos) > 0)
                <div class="item">
                    <a href="">
                        <p class="imgbox" style="background: url(https://ss3.bdstatic.com/70cFv8Sh_Q1YnxGkpoWK1HF6hhy/it/u=2200166214,500725521&fm=27&gp=0.jpg) no-repeat center; background-size: cover;"></p>
                        <p class="con">罗纳尔多原告的律师要</p>
                    </a>
                </div>
            @endif
        </div>
    </div>
@stop

@section('js')
    <script type="text/javascript" src="/js/public/mobile/team.js"></script>
    <script type="text/javascript">
        window.onload = function(){
            setPage();
        }
    </script>
@stop