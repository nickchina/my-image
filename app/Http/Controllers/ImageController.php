<?php

namespace App\Http\Controllers;

use \File;
use \Image;
use \Formatter;

/**
 * 缩略图处理类
 * @author Nick
 *
 * Class ImageController
 * @package App\Http\Controllers
 */
class ImageController {

    const KEY_CODE = 'NoSuchKey';
    const KEY_ERROR_MSG = 'The specified key does not exist.';

    const ARGUMENT_CODE = 'InvalidArgument';
    const ARGUMENT_ERROR_MSG = 'The value: %s of parameter: %s is invalid.';

    protected $image; //请求的图片
    protected $thumb; //缩略图路径
    private $_width = ''; //目标宽
    private $_height = ''; //目标高
    private $_cut = ''; //是否裁剪
    private $_extend = ''; //是否缩放

    /**
     * 预处理
     * @author Nick
     *
     * @param string $key url参数
     * @return string|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function process($key) {
        //处理url参数
        if (true !== $response = $this->_processParam($key))
            return $response;

        //请求的图片不存在
        if (false === File::exists($this->image))
            return $this->_error(self::KEY_CODE, self::KEY_ERROR_MSG, $this->image);

        //生成缩略图
        $this->_processThumb();

        return response()->download($this->thumb);
    }

    /**
     * 处理缩略图参数
     * @author Nick
     *
     * @param string $key
     * @return bool|mixed
     */
    private function _processParam($key) {
        $arr = explode('@', $key);
        if (1 == count($arr))
            return $this->_error(self::KEY_CODE, self::KEY_ERROR_MSG, $key);
        $this->image = $arr[0];
        $ruleArray = explode('_', $arr[1]);

        //组织缩略图路径
        foreach ($ruleArray as $value) {
            if (empty($this->_width)) {
                //指定目标缩略图的宽度
                preg_match('/.+w/', $value, $matchW);
                !empty($matchW) && $this->_width = rtrim($matchW[0], 'w');
            }
            if (empty($this->_height)) {
                //指定目标缩略图的高度。
                preg_match('/.+h/', $value, $matchH);
                !empty($matchH) && $this->_height = rtrim($matchH[0], 'h');
            }
            if (empty($this->_cut)) {
                //是否进行裁剪。如果是想对图进行自动裁剪，必须指定为1
                preg_match('/.+c/', $value, $matchC);
                !empty($matchC) && $this->_cut = rtrim($matchC[0], 'c');
            }
            if (empty($this->_extend)) {
                //缩放优先边，这里指定按短边优化
                preg_match('/.+e/', $value, $matchE);
                !empty($matchE) && $this->_extend = rtrim($matchE[0], 'e');
            }
        }

        if (!is_numeric($this->_width))
            return $this->_error(self::ARGUMENT_CODE, vsprintf(self::ARGUMENT_ERROR_MSG, [$this->_width, 'w']));
        elseif (!is_numeric($this->_height))
            return $this->_error(self::ARGUMENT_CODE, vsprintf(self::ARGUMENT_ERROR_MSG, [$this->_height, 'h']));
        elseif (!in_array($this->_cut, ['0', '1']))
            return $this->_error(self::ARGUMENT_CODE, vsprintf(self::ARGUMENT_ERROR_MSG, [$this->_cut, 'c']));
        elseif (!in_array($this->_extend, ['0', '1']))
            return $this->_error(self::ARGUMENT_CODE, vsprintf(self::ARGUMENT_ERROR_MSG, [$this->_extend, 'e']));
        else
        {
            $rule = "{$this->_width}w_{$this->_height}h_{$this->_cut}c_{$this->_extend}e";
            $pathinfo = pathinfo($this->image);
            $this->thumb = "thumb/{$pathinfo['dirname']}/{$pathinfo['filename']}@{$rule}.jpg";
        }
        return true;
    }

    /**
     * 处理缩略图
     *
     */
    private function _processThumb() {
        //读取图片
        $image = Image::make($this->image);
        $realW = $image->getWidth(); //实际宽度
        $realH = $image->getHeight(); //实际高度

        //缩放
        if (1 == $this->_extend) { //按短边缩放
            $realW < $realH ? $image->widen($this->_width) : $image->heighten($this->_height);
        }
        else { //按长边缩放
            $realW < $realH ? $image->heighten($this->_height) : $image->widen($this->_width);
        }
        //裁剪，非短边缩放不裁剪
        if (1 == $this->_cut && 1 == $this->_extend) {
            $image->fit($this->_width, $this->_height);
        }

        //保存图片
        $dir = pathinfo($this->thumb, PATHINFO_DIRNAME);
        File::isDirectory($dir) || File::makeDirectory($dir, 0755, true, true);
        $image->save($this->thumb);
    }

    /**
     * 处理错误信息
     * @author Nick
     *
     * @param $code
     * @param $message
     * @param $key
     * @return mixed
     */
    private function _error($code, $message, $key = '') {
        $content['Error']['Code'] = $code;
        $content['Error']['Message'] = $message;
        !empty($key) && $content['Error']['Key'] = $key;
        return response(Formatter::make($content, Formatter::XML)->toXml())->header('Content-Type', 'application/xml');
    }
}