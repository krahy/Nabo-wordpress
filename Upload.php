<?php

/**
 * 上传组件
 *
 * @Author 陆之岇(Kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team
 */
class Nabo_Upload
{
    /**
     * @throws Exception
     */
    public function launch()
    {
        // name
        $name = $_POST['name'];

        // token
        $token = $_POST['token'];

        // check
        if (empty($name) || empty($token)) {
            $this->response('缺少必要参数');
        }

        // report
        error_reporting(E_ERROR);

        include_once 'Kat/Kat.php';
        include_once 'Kat/Plus.php';

        include_once 'User.php';
        include_once 'Format.php';

        $liteuser = new Nabo_User();
        $liteuser->identity([
            'name' => $name,
            'token' => $token
        ]);

        try {
            $liteuser->register();
        } catch (Exception $e) {
            $this->response(
                $e->getMessage()
            );
        }

        if (!$liteuser->pairing()) {
            $this->response('权限不足');
        }

        try {
            $this->upload();
        } catch (Exception $e) {
            $this->response(
                $e->getMessage()
            );
        }
    }

    /**
     * @target upload
     */
    public function upload()
    {
        // check
        if (!current_user_can('upload_files')) {
            $this->response('无权限');
        }

        if (empty($_FILES)) {
            $this->response('不存在文件');
        }

        // select
        $file = array_pop($_FILES);
        if ($file['error'] != 0 || !is_uploaded_file($file['tmp_name'])) {
            $this->response('文件异常');
        }

        // check
        $upload_err = apply_filters(
            'pre_upload_error', false
        );
        if ($upload_err) {
            $this->response(
                (string)$upload_err
            );
        }

        // upload
        $upload = wp_upload_bits(
            $file['name'], null,
            file_get_contents($file['tmp_name'])
        );

        // check
        if (!empty($upload['error'])) {
            $errorString = sprintf('Could not write file %1$s (%2$s).', $file['name'], $upload['error']);
            $this->response($errorString);
        }

        $post_id = 0;
        $attachment = array(
            'guid' => $upload['url'],
            'post_title' => $file['name'],
            'post_content' => '',
            'post_type' => 'attachment',
            'post_parent' => $post_id,
            'post_mime_type' => $file['type'],
        );

        // Save the data.
        $id = wp_insert_attachment(
            $attachment, $upload['file'], $post_id
        );
        if (empty($id)) {
            $this->response('文件数据入库失败');
        }

        $this->response(
            '上传成功', 1,
            Nabo_Format::media(
                get_post($id)
            )
        );
    }

    /**
     * @param $msg
     * @param int $code
     * @param null $data
     */
    public function response($msg, $code = 0, $data = null)
    {
        kat_response([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]);
    }
}
