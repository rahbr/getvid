<?php
namespace Youtube;

use Youtube\Models\ItagInfoModel;
use Youtube\Helpers\YoutubeHelper;
class YoutubeDownloader
{

    protected $url;
    protected $fullInfo;
    protected $videoId;

    public function __construct($url)
    {
        $this->url = $url;
        $this->getVideoId($url);
    }

    public function getVideoId($url)
    {
        preg_match("/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/i", $this->url, $matches);
        $this->videoId = $matches[7];
        return $this->videoId;
    }

    public function getFullInfo()
    {
        if (is_null($this->fullInfo)) {
            $clearContent = file_get_contents(
                sprintf("https://www.youtube.com/watch?v=%s", $this->videoId)
            );
            preg_match('/ytplayer.config = (\{.+});ytplayer/', $clearContent, $mathes);
            $configData = json_decode($mathes[1], true);

            $streamList = explode(',', $configData['args']['url_encoded_fmt_stream_map']);
            $configData['args']['url_encoded_fmt_stream_map'] = array();
            foreach ($streamList AS $stream) {
                parse_str($stream, $res);
                $configData['args']['url_encoded_fmt_stream_map'][] = $res;
            }

            $streamList = explode(',', $configData['args']['adaptive_fmts']);
            $configData['args']['adaptive_fmts'] = array();
            foreach ($streamList as $stream) {
                parse_str($stream, $res);
                $configData['args']['adaptive_fmts'][] = $res;
            }

            $this->fullInfo = $configData['args'];
            $this->fullInfo['html'] = $clearContent;
            $this->fullInfo['video_id'] = $this->videoId;
            $jsPath = $configData['assets']['js'];
            $jsPlayerClear = file_get_contents('https://www.youtube.com' . $jsPath);

            preg_match('/set\("signature",\s*(?:([^(]*).*)\)/', $jsPlayerClear, $mathes);
            $signParserFnName = (count($mathes) > 0) ? $mathes[1] : null;

            preg_match("/$signParserFnName=(function\(.+;)/", $jsPlayerClear, $mathes);
            $signFnBody = (count($mathes) > 0) ? $mathes[1] : null;

            preg_match("/(\w+)\.\w+\([\w\d,]+\)/", $signFnBody, $mathes);
            $helperObjName = (count($mathes) > 0) ? $mathes[1] : null;

            preg_match_all("/$helperObjName\.(\w+)\(\w+,(\d+)\);/", $signFnBody, $matches);
            $startPos = strpos($jsPlayerClear, "$helperObjName={");
            $calcRule = array();
            foreach ($matches[1] AS $i => $fnName) {
                $calcRule[] = array(
                    'function' => $fnName,
                    'param' => $matches[2][$i],
                );
            }
            $endPos = $startPos + strlen($helperObjName) + 2;
            $startPos += strlen($helperObjName) + 1;
            $countOpenBraces = 1;
            while ($countOpenBraces > 0) {
                $char = substr($jsPlayerClear, $endPos, 1);
                $endPos++;
                if ($char == '{') {
                    $countOpenBraces++;
                } elseif ($char == '}') {
                    $countOpenBraces--;
                }
            }
            $helperObjBody = substr($jsPlayerClear, $startPos, $endPos - $startPos);
            preg_match_all('/(\w+):function\([\w,]+\){([^}]+)}/', $helperObjBody, $matches);
            $calcFunctions = array();
            foreach ($matches[1] AS $i => $fnName) {
                $fnBody = $matches[2][$i];
                if (strpos($fnBody, 'reverse') !== false) {
                    $calcFunctions[$fnName] = 'reverse';
                    continue;
                }
                if (strpos($fnBody, 'splice') !== false) {
                    $calcFunctions[$fnName] = 'splice';
                    continue;
                }
                $calcFunctions[$fnName] = 'swap';
            }
            foreach ($calcRule AS &$calcItem) {
                $calcItem['function'] = $calcFunctions[$calcItem['function']];
            }
            $this->fullInfo['calcSignatureSteps'] = $calcRule;
            $calcSignExpression = <<<JS
    function calculateSignature(sign){
        var __HELPER_OBJ_NAME = __HELPER_OBJ_BODY__;
        var calcSign = __SIGN_FN_BODY__;
        return calcSign(sign);
    }
JS;
            $calcSignExpression = str_replace('__HELPER_OBJ_NAME', $helperObjName, $calcSignExpression);
            $calcSignExpression = str_replace('__HELPER_OBJ_BODY__', $helperObjBody, $calcSignExpression);
            $calcSignExpression = str_replace('__SIGN_FN_BODY__', $signFnBody, $calcSignExpression);

            $this->fullInfo['jsSignatureGen'] = $calcSignExpression;
        }
        return $this->fullInfo;
    }

    public function getBaseInfo()
    {
        $fullInfo = $this->getFullInfo();
        $baseInfo = array();
        $baseInfo['name'] = $fullInfo['title'];
        $videoId = $fullInfo['video_id'];
        $baseInfo['previewUrl'] = "https://img.youtube.com/vi/$videoId/hqdefault.jpg";
        $html = $fullInfo['html'];
        if (preg_match('/<p id="eow-description"[^>]+>(.+)<\/p>/', $html, $matches)) {
            $baseInfo['description'] = strip_tags(str_replace('<br />', "\n", $matches[1]));
        }
        return $baseInfo;
    }

    static protected function calcSignature($signatureEncoded, $steps)
    {
        $sign = str_split($signatureEncoded);
        foreach ($steps AS $step) {
            $fn = $step['function'];
            $param = (int) $step['param'];

            switch ($fn) {
                case 'reverse':
                    $sign = array_reverse($sign);
                    break;
                case 'splice':
                    $sign = array_slice($sign, $param);
                    break;
                case 'swap':
                    $c = $sign[0];
                    $sign[0] = $sign[$param % count($sign)];
                    $sign[$param] = $c;
                    break;
            }
        }
        return implode('', $sign);
    }

    public static function getResponseHeaders($url)
    {
        $clearHeaders = get_headers($url);
        $headers = array();
        foreach ($clearHeaders AS $header) {
            $header = explode(':', $header);
            if (count($header) === 2) {
                $headers[$header[0]] = trim($header[1]);
            }
        }
        return $headers;
    }

    public function getDownloadInfoOne($fmtsItem)
    {
        $fullInfo = $this->getFullInfo();
        $title = $fullInfo['title'];
        $url = $fmtsItem['url'] . '&title=' . urlencode($title);
        $signature = false;
        if (isset($fmtsItem['signature'])) {
            $signature = $fmtsItem['signature'];
        }

        if (isset($fmtsItem['sig'])) {
            $signature = self::calcSignature($fmtsItem['sig'], $fullInfo['calcSignatureSteps']);
        }

        if (isset($fmtsItem['s'])) {
            $signature = self::calcSignature($fmtsItem['s'], $fullInfo['calcSignatureSteps']);
        }

        if ($signature) {
            $url .= '&signature=' . $signature;
        }

        $headers = self::getResponseHeaders($url);
        //$downloadInfo = raray();
        $downloadInfo['fileSize'] = (int) $headers['Content-Length'];
        $downloadInfo['fileSizeHuman'] = YoutubeHelper::getFileSizeHuman($downloadInfo['fileSize']);
        $downloadInfo['url'] = $url;
        $downloadInfo['youtubeItag'] = $fmtsItem['itag'];
        $downloadInfo['fileType'] = explode(';', $fmtsItem['type']);
        $downloadInfo['fileType'] = $downloadInfo['fileType'][0];
        $ext = explode('/', $downloadInfo['fileType']);
        $ext = $ext[1];
        $downloadInfo['name'] = $title . '.' . $ext;
        $downloadInfo['itagInfo'] = new ItagInfoModel($fmtsItem['itag']);
        
        $downloadInfo[] = $downloadInfo;
        return $downloadInfo;
    }

    /**
     * Return array of download information for video
     *
     * @return VideoDownloadInfo[]
     * @throws VideoDownloaderDownloadException
     */
    public function getDownloadsInfo()
    {
        $fullInfo = $this->getFullInfo();
        $fmts = $fullInfo['url_encoded_fmt_stream_map'];
        $fmts = array_merge($fmts, $fullInfo['adaptive_fmts']);
        $downloadsInfo = array();
        foreach ($fmts AS $item) {
            $downloadsInfo[] = $this->getDownloadInfoOne($item);
        }
        return $downloadsInfo;
    }

    public function downloadForItag($itag)
    {
        $fullInfo = $this->getFullInfo();
        $fmts = $fullInfo['url_encoded_fmt_stream_map'];
        $fmts = array_merge($fmts, $fullInfo['adaptive_fmts']);
        foreach ($fmts as $item) {
            if ($item['itag'] == $itag) {
                $dlInfo = $this->getDownloadInfoOne($item);
                set_time_limit(0);
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                $qwoteName = str_replace('"', '', $dlInfo['name']);
                header('Content-Disposition: attachment; filename="' . $qwoteName . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . $dlInfo['fileSize']);
                ob_clean();
                flush();
                readfile($dlInfo['url']);
                die();
            }
        }
        header('Not found', true, 404);
        echo "404 Not found";
        die();
    }
}
