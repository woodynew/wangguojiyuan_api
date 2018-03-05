<?php
/**
 * User: Administrator
 * Date: 2016/12/15
 * Time: 15:34
 */

namespace App\Jobs\APICmds\Applet\WeiXin;


use App\JsonParse\JErrorCode;
use App\Models\GameBbs;
use App\Models\GameBbsLike;
use App\Models\WXUser;
use App\Util\TimeUtil;

class ALWGameBbsCmd extends BaseCmd
{
    public function __construct($jsonData)
    {
        parent::__construct($jsonData);
        $this->logPath .= 'gamebbs/';
    }

    public function addGameBbs()
    {
        $data = $this->jsonData;
        try {
            if (!isset($data->content) || !isset($data->photoList))
                return $this->error(JErrorCode::LACK_PARAM_ERROR);

            $wxUser = WXUser::where('cl_OpenId', $data->m_openId)->first();
            if (empty($wxUser))
                return $this->error(JErrorCode::WX_USER_INFO_NOT_FOUND_ERROR);

            $user = $wxUser->user;

            $modifyValue = 300; //压缩图最长边px
            $thumbArr = [];
            foreach ($data->photoList as $item) {
                $pathSplitArr = explode('.', $item);

                $tPath = $pathSplitArr[0] . '_thumb.' . $pathSplitArr[1];
                $absoluteTPath = public_path() . $tPath;

                $thumbArr[] = $tPath;

                $img = \Image::make(public_path() . $item);

                $width = $img->width();
                $height = $img->height();

                if ($width > $height && $width > $modifyValue)
                    $img->widen($modifyValue);
                else if ($height > $width && $height > $modifyValue)
                    $img->heighten($modifyValue);

                $img->save($absoluteTPath);
            }

            $modelData = [
                'cl_UserId' => $user->user_id,
                'cl_CreateTime' => TimeUtil::getChinaTime(),
                'cl_Content' => $data->content,
                'cl_Photos' => implode(',', $data->photoList),
                'cl_Thumbs' => implode(',', $thumbArr),
            ];

            GameBbs::create($modelData);

            return $this->success();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function getGameBbs()
    {
        $data = $this->jsonData;
        try {
            if (!isset($data->pageIndex) || !isset($data->pageSize) || !isset($data->orderType))
                return $this->error(JErrorCode::LACK_PARAM_ERROR);

            $wxUser = WXUser::where('cl_OpenId', $data->m_openId)->first();
            if (empty($wxUser))
                return $this->error(JErrorCode::WX_USER_INFO_NOT_FOUND_ERROR);

            $user = $wxUser->user;

            $dataList = GameBbs::valid()->orderType($data->orderType)->page($data->pageIndex)->paginate($data->pageSize);
            foreach ($dataList as $item) {

                $this->result_list[] = $this->setGameBbsInfo($item, $user->user_id);

            }

            return $this->result();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function getGameBbsDetail()
    {
        $data = $this->jsonData;
        try {
            if (!isset($data->bbsId))
                return $this->error(JErrorCode::LACK_PARAM_ERROR);

            $wxUser = WXUser::where('cl_OpenId', $data->m_openId)->first();
            if (empty($wxUser))
                return $this->error(JErrorCode::WX_USER_INFO_NOT_FOUND_ERROR);

            $user = $wxUser->user;

            $bbs = GameBbs::find($data->bbsId);
            $this->result_param = $this->setGameBbsInfo($bbs, $user->user_id);

            return $this->result();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function likeGameBbs()
    {
        $data = $this->jsonData;
        try {
            if (!isset($data->bbsId))
                return $this->error(JErrorCode::LACK_PARAM_ERROR);

            $wxUser = WXUser::where('cl_OpenId', $data->m_openId)->first();
            if (empty($wxUser))
                return $this->error(JErrorCode::WX_USER_INFO_NOT_FOUND_ERROR);

            $user = $wxUser->user;

            $bbs = GameBbs::find($data->bbsId);
            $bbs->increment('cl_Like');

            $modelData = [
                'cl_BbsId' => $bbs->cl_Id,
                'cl_UserId' => $user->user_id,
                'cl_CreateTime' => TimeUtil::getChinaTime()
            ];

            GameBbsLike::create($modelData);

            return $this->success();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    private function setGameBbsInfo($item, $iUserId, $type = 1)
    {
        $result_param['id'] = $item->cl_Id;

        $result_param['content'] = $item->cl_Content;
        $result_param['userName'] = $item->user->alias;
        $result_param['userHead'] = $item->user->getHeadImg();
        $result_param['time'] = $item->cl_CreateTime;
        $result_param['like'] = $item->cl_Like;
        $result_param['comment'] = $item->cl_Comment;
        $result_param['isHot'] = $item->cl_IsHot;
        $result_param['photos'] = $item->getPhotoArr();
        $result_param['thumbs'] = $item->getThumbArr();
        $result_param['isLike'] = $item->isLike($iUserId);

        if ($type == 1)
            return json_decode(json_encode($result_param));
        return $result_param;
    }
}