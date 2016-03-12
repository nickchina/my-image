<?php

namespace App\Http\Controllers;

use \File;
use \Image;
use SoapBox\Formatter\Formatter;

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

    protected $path; //请求图片路径
    protected $size; //待处理裁剪信息
    protected $dirname; //缩略图存储目录
    protected $filename; //缩略图名称
    protected $filepath; //路径+名称

    /**
     * 预处理
     * @author Nick
     *
     * @param $key url参数
     * @return string|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function process($key) {
        //处理url参数
        $arr = explode('@', $key);
        $this->path = $arr[0];
        $this->size = $arr[1];

        //查找请求文件
        if (false === File::exists($this->path))
            return $this->error(self::KEY_CODE, self::KEY_ERROR_MSG, $key);

        //组织缩略图路径
        $this->dirname = 'thumb/' . pathinfo($this->path, PATHINFO_DIRNAME);
        $this->filename = pathinfo($this->path, PATHINFO_FILENAME) . "@$this->size";
        $this->filepath = "$this->dirname/$this->filename.jpg";

        //生成缩略图
        if (true !== $response = $this->processParam())
            return $response;
        elseif (File::exists($this->filepath))
            return response()->download($this->filepath);
    }

    /**
     * 处理缩略图参数
     * @author Nick
     *
     * @return bool|mixed
     */
    private function processParam() {
        $width = $height = $cut = $extend = '';
        $tmp = explode('_', $this->size);
        foreach ($tmp as $value) {
            if (empty($width)) {
                //指定目标缩略图的宽度
                preg_match('/.+w/', $value, $matchW);
                !empty($matchW) && $width = rtrim($matchW[0], 'w');
            }
            if (empty($height)) {
                //指定目标缩略图的高度。
                preg_match('/.+h/', $value, $matchH);
                !empty($matchH) && $height = rtrim($matchH[0], 'h');
            }
            if (empty($cut)) {
                //是否进行裁剪。如果是想对图进行自动裁剪，必须指定为1
                preg_match('/.+c/', $value, $matchC);
                !empty($matchC) && $cut = rtrim($matchC[0], 'c');
            }
            if (empty($extend)) {
                //缩放优先边，这里指定按短边优化
                preg_match('/.+e/', $value, $matchE);
                !empty($matchE) && $extend = rtrim($matchE[0], 'e');
            }
        }

        if (!is_numeric($width))
            return $this->error(self::ARGUMENT_CODE, vsprintf(self::ARGUMENT_ERROR_MSG, [$width, 'w']));
        elseif (!is_numeric($height))
            return $this->error(self::ARGUMENT_CODE, vsprintf(self::ARGUMENT_ERROR_MSG, [$height, 'h']));
        elseif (!in_array($cut, ['0', '1']))
            return $this->error(self::ARGUMENT_CODE, vsprintf(self::ARGUMENT_ERROR_MSG, [$cut, 'c']));
        elseif (!in_array($extend, ['0', '1']))
            return $this->error(self::ARGUMENT_CODE, vsprintf(self::ARGUMENT_ERROR_MSG, [$extend, 'e']));
        else
            $this->processThumb($width, $height, $cut, $extend);
        return true;
    }

    /**
     * 处理缩略图
     *
     * @param $width
     * @param $height
     * @param $cut
     * @param $extend
     */
    private function processThumb($width, $height, $cut, $extend) {
        //读取图片
        $image = Image::make($this->path);
        $realW = $image->getWidth(); //实际宽度
        $realH = $image->getHeight(); //实际高度

        //缩放
        if (1 == $extend) { //按短边缩放
            $realW < $realH ? $image->widen($width) : $image->heighten($height);
        }
        else { //按长边缩放
            $realW < $realH ? $image->heighten($height) : $image->widen($width);
        }
        //裁剪，非短边缩放不裁剪
        if (1 == $cut && 1 == $extend) {
            $image->fit($width, $height);
        }

        //保存图片
        File::isDirectory($this->dirname) || File::makeDirectory($this->dirname, 0755, true, true);
        $image->save($this->filepath);
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
    private function error($code, $message, $key = '') {
        $content['Error']['Code'] = $code;
        $content['Error']['Message'] = $message;
        !empty($key) && $content['Error']['Key'] = $key;
        return response(Formatter::make($content, Formatter::XML)->toXml())->header('Content-Type', 'application/xml');
    }
}