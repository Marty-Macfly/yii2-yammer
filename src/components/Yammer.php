<?php

namespace macfly\yammer\components;

use Yii;
use yii\httpclient\Client;
use yii\helpers\ArrayHelper;

class Yammer extends Client
{
    public $baseUrl = 'https://www.yammer.com/api/v1';
    public $token   = null;

    public function beforeSend($request)
    {
        parent::beforeSend($request);

        if($this->token !== null)
        {
            $request->addHeaders([
                'Authorization' => sprintf("Bearer %s", $this->token),
            ]);
        }
    }

    private function to_array($mixed)
    {
        return is_array($mixed) ? array_unique($mixed,SORT_STRING) : (is_null($mixed) ? [] : [ $mixed ]);
    }

    private function array_prefix($array, $prefix = '')
    {
        $array = array_flip($array);
        array_walk($array, function (&$v, $k, $prefix) {
            $v = sprintf("%s%d", $prefix,$v + 1);
        }, $prefix);
        return array_flip($array);
    }

    public function sendFiles($files)
    {
        $files      = $this->to_array($files);
        $requests   = [];

        foreach($files as $file)
        {
            $requests[$file] = $this->post('/pending_attachments')->addFile('attachment', $file);
        }

        $responses  = $this->batchSend($requests);
        $ids        = [];

        foreach($responses as $file => $rs)
        {
            if($rs->isOk && ($id = ArrayHelper::getValue($rs->data, 'id')) !== null)
            {
                $ids[$file] = $id;
            }
            else
            {
                Yii::error(sprintf("Upload of '%s' failed status code: %s => %s", $file, $rs->statusCode, $rs->content));
                $ids[$file] = false;
            }
        }

        return $ids;
    }

    public function deleteFiles($ids)
    {
        $ids        = $this->to_array($ids);
        $requests   = [];

        foreach($ids as $id)
        {
            $requests[$id] = $this->delete(sprintf('/uploaded_files/%d', $id));
        }

        $responses  = $this->batchSend($requests);
        $ids        = [];
        foreach($responses as $id => $rs)
        {
            $ids[$id] = $rs->isOk;
            if(!$rs->isOk)
            {
                Yii::error(sprintf("Unable to delete file id: %d => %s - %s", $id, $rs->statusCode, $rs->content));
            }
        }

        return $ids;
    }

    public function sendAnnouncement($body, $title = null, $tags = [], $files = [], $options = [])
    {
        return $this->sendMessage($body, $title, $tags, $files, array_merge(
            $options,
            [
                'is_rich_text'  => 'true',
                'message_type'  => 'announcement',
            ]));
    }

    public function sendMessageToNetworks($ids, $body, $title = null, $tags = [], $files = [], $options = [])
    {
        $ids        = $this->to_array($ids);
        $results    = [];
        foreach($ids as $id)
        {
            $results[$id] = $this->sendMessageToNetwork($id, $body, $title, $tags, $files, $options);
        }
        return $results;
    }

    public function sendMessageToNetwork($id, $body, $title = null, $tags = [], $files = [], $options = [])
    {
        if($id !== null)
        {
            $options['network_id'] = $id;
        }

        return $this->sendMessage($body, $title, $tags, $files, $options);
    }

    public function sendMessageToGroups($ids, $body, $title = null, $tags = [], $files = [], $options = [])
    {
        $ids        = $this->to_array($ids);
        $results    = [];
        foreach($ids as $id)
        {
            $results[$id] = $this->sendMessageToGroup($id, $body, $title, $tags, $files, $options);
        }
        return $results;
    }

    public function sendMessageToGroup($id, $body, $title = null, $tags = [], $files = [], $options = [])
    {
        $options['group_id'] = $id;
        return $this->sendMessage($body, $title, $tags, $files, $options);
    }

    public function sendMessageToUsers($ids, $body, $title = null, $tags = [], $files = [], $options = [])
    {
        return $this->sendMessageToUser($ids, $body, $title, $tags, $files, $options);
    }

    public function sendMessageToUser($id, $body, $title = null, $tags = [], $files = [], $options = [])
    {
        $options['direct_to_user_ids'] = $this->to_array($id);
        return $this->sendMessage($body, $title, $tags, $files, $options);
    }

    public function sendMessageToMessages($ids, $body, $title = null, $tags = [], $files = [], $options = [])
    {
        $ids        = $this->to_array($ids);
        $results    = [];
        foreach($ids as $id)
        {
            $results[$id] = $this->sendMessageToMessage($id, $body, $title, $tags, $files, $options);
        }
        return $results;
    }

    public function sendMessageToMessage($id, $body, $title = null, $tags = [], $files = [], $options = [])
    {
        $options['replied_to_id'] = $id;
        return $this->sendMessage($body, $title, $tags, $files, $options);
    }

    public function sendMessage($body, $title = null, $tags = [], $files = [], $options = [])
    {
        $tags   = $this->to_array($tags);
        $files  = $this->to_array($files);
        $length = 20;
        $data   = [
            'body'  => $body,
        ];

        if($title !== null)
        {
            $data['title'] = $title;
        }

        $data   = array_merge(
            $data,
            $this->array_prefix(array_slice($tags, 0, $length), 'topic'),
            $this->array_prefix(array_values($this->sendFiles(array_slice($files, 0, $length))), 'pending_attachment'),
            $options
        );

        print_r($data);

        $rs = $this->post('/messages.json', $data)->send();

        if($rs->isOk && ($id = ArrayHelper::getValue($rs->data, 'messages.0.id')) !== null)
        {
            echo $id . "\n";
            if(max(ceil(count($tags) / $length), ceil(count($files) / $length)) > 1)
            {
                $this->sendMessageToMessage(
                    $id,
                    '',
                    null,
                    array_slice($tags, $length),
                    array_slice($files, $length),
                    $options
                );
            }
            return $id;
        }
        else
        {
            Yii::error(sprintf("Failed to send message status code: %s => %s", $rs->statusCode, $rs->content));
            return false;
        }

    //'og_url'            => 'http://www.linkbynet.com/',
    //'og_fetch'          => 'true',
    //'og_title'          => 'OG title',
    //'og_site_name'      => 'Site name',
    //'og_object_type'    => 'image',
    //'og_description'    => 'Linkbynet site web',
    //'og_image'          => 'https://a248.e.akamai.net/assets.github.com/images/modules/dashboard/octofication.png',
    }
}
