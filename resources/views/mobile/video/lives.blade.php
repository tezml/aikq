@extends('mobile.layout.base')
@section("body_attr") onscroll="scrollBottom(loadVideos);" @endsection
@section('title')
    <title>爱看球-JRS|JRS直播|NBA直播|NBA录像|CBA直播|英超直播|西甲直播|低调看|直播吧|CCTV5在线</title>
@endsection

@section('css')
    <link rel="stylesheet" type="text/css" href="{{env('CDN_URL')}}/css/mobile/videoList.css?rd=201804">
    <style>
        #Navigation {
            background: #4492fd;
        }
    </style>
@endsection

@section('banner')
    <div id="Navigation">
        <div class="banner">
            <!-- <p class="type"><button class="on" id="Football" name="type">足球</button><button id="Basketball" name="type">篮球</button><button id="Other" name="type">其他</button></p> -->
            <img src="{{env('CDN_URL')}}/img/mobile/image_slogan_nav.png">
        </div>
    </div>
@endsection

@section('content')
    <a href="/downloadPhone.html"><img style="width: 100%" src="/img/mobile/image_ad_wap.jpg"></a>
    <?php $week_array = array('周日','周一','周二','周三','周四','周五','周六'); ?>
    @foreach($matches as $time=>$match_array)
        <?php $week = date('w', strtotime($time)); ?>
        <div class="default">
            <p class="day" day="{{$time}}">{{$time}}&nbsp;&nbsp;{{$week_array[$week]}}</p>
            @foreach($match_array as $match)
                <a href="{{\App\Http\Controllers\PC\MatchTool::subjectLink($match['id'], 'video')}}">
                    <p class="time">{{$match['lname']}}&nbsp;&nbsp;{{date('H:i', $match['time'])}}</p>
                    <p class="other">{{$match['hname'] . ' ' . $match['hscore'] . ' - ' . $match['ascore'] . ' ' . $match['aname']}}</p>
                </a>
            @endforeach
        </div>
    @endforeach
    <div class="nolist separated">暂时无直播比赛</div>
    <p id="PC"><a href="/downloadPhone.html">下载爱看球APP，流畅度快3倍<br/>www.aikq.cc</a><button class="close" onclick="this.parentNode.style.display='none'"></button></p>
@endsection

@section('bottom')
    <dl id="Bottom">
        <dd>
            <a href="/m/lives.html">
                <img src="{{env('CDN_URL')}}/img/mobile/commom_icon_live_n.png">
                <p>直播</p>
            </a>
        </dd>
        {{--<dd class="">--}}
            {{--<a href="/m/anchor/index.html">--}}
                {{--<img src="{{env('CDN_URL')}}/img/mobile/commom_icon_anchor_n.png">--}}
                {{--<p>主播</p>--}}
            {{--</a>--}}
        {{--</dd>--}}
        <dd class="on">
            <a href="">
                <img src="{{env('CDN_URL')}}/img/mobile/commom_icon_vedio_s.png">
                <p>录像</p>
            </a>
        </dd>
        <dd>
            <a href="https://shop.liaogou168.com">
                <img src="{{env('CDN_URL')}}/img/mobile/commom_icon_recommend_n.png">
                <p>推荐</p>
            </a>
        </dd>
    </dl>
@endsection

@section('js')
    <script type="text/javascript">
        window.curPage = '{{$page['curPage']}}';
        window.loadPage = false;
        function changeTab(tab) {
            switch (tab){
                case 'all':
                    window.location.replace('/m');
                    break;
                case 'football':
                    window.location.replace('/m/football.html');
                    break;
                case 'basketball':
                    window.location.replace('/m/basketball.html');
                    break;
                case 'other':
                    window.location.replace('/m/other.html');
                    break;
                case 'live':
                    window.location.replace('/m/index.html');
                    break;
                case 'video':
                    window.location.replace('/m/live/subject/videos/all/1.html');
                    break;
            }
        }
    </script>
    <script type="text/javascript" src="/js/public/mobile/subjectVideo.js"></script>
@endsection