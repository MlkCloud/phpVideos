<?php
/**
 * Created by PhpStorm.
 * User: mickey
 * Date: 2018/7/5
 * Time: 15:41
 */
namespace core\Platform\Pornhub;

use core\Cache\FileCache;
use core\Common\ArrayHelper;
use core\Common\Downloader;
use core\Http\Curl;

class Pornhub extends Downloader
{
    public $pornhubHtmlFile = './pornhub.html';

    public function __construct($url)
    {
        $this->requestUrl = $url;
    }

    /**
     * @throws \ErrorException
     */
    public function getVideosJson()
    {
        $videosJsonCache = (new FileCache())->get($this->requestUrl);
        if($videosJsonCache){
            return $videosJsonCache;
        }
        //PHP cUrl 不做代理 ，直接走本地
        exec("curl {$this->requestUrl} > pornhub.html", $curlHtml);

        $htmlErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTMLFile($this->pornhubHtmlFile);
        $player = $dom->getElementById('player');
        $videosId = $player->getAttribute('data-video-id');

        libxml_use_internal_errors($htmlErrors);

        $javaScript = $player->nodeValue;

        if(!$videosId){
            throw new \ErrorException('无法解析该视频');
        }

        $patter = "/flashvars_{$videosId} = (.*?)};/is";
        preg_match_all($patter, $javaScript, $matches);


        if(!isset($matches[1][0])){
            throw new \ErrorException('无法解析该视频真实地址');
        }

        unset($dom);
        unlink($this->pornhubHtmlFile);
        (new FileCache())->set($this->requestUrl, $matches[1][0] . '}');

        return $matches[1][0] . '}';
    }

    public function getVideosList($videosJson)
    {
        $videosLists = json_decode($videosJson, true);

        $this->setVideosTitle($videosLists['video_title']);

        $videosList = array_filter($videosLists['mediaDefinitions'], function($var){
            if(!empty($var['videoUrl'])){
                return $var;
            }
        });

        return $videosList;
    }


    /**
     * @throws \ErrorException
     */
    public function download()
    {
        $videosJson = $this->getVideosJson();
        $videosList = $this->getVideosList($videosJson);

        if(!$videosList){
            echo PHP_EOL . 'No video found'. PHP_EOL;
            exit(0);
        }

        $videosList = ArrayHelper::multisort($videosList, 'quality', SORT_DESC);

        $this->videoQuality = $videosList[0]['quality'];
        $this->downloadFile($videosList[0]['videoUrl'], $this->videosTitle);
        $this->success();
    }
}