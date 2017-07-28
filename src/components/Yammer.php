<?php

namespace macfly\yammer\components;

use Yii;
use yii\httpclient\Client;
use yii\helpers\ArrayHelper;

use macfly\yammer\models\OpenGraph;

class Yammer extends Client
{
    public $baseUrl     = 'https://www.yammer.com/api/v1';
    public $token       = null;

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
        $requests  = array_map(function ($file) {
            return $this->post('pending_attachments')
                ->addFile('attachment', $file);
        }, $this->to_array($files));

        $callback   = function ($rs) {
            if(!($rs->isOk && ($id = ArrayHelper::getValue($rs->data, 'id')) !== null))
            {
                \Yii::error(sprintf("[%s] failed %d => %s", $rs->headers->get('location'), $rs->statusCode, $rs->content));
            }
            return $id;
        };

        return $this->batchRequest($requests, $callback);
    }

    public function sendFile($file)
    {
        return array_pop($this->sendFiles($file));
    }

    public function deleteFiles($ids)
    {
        $requests  = array_map(function ($id) {
            return $this->delete(sprintf("uploaded_files/%d", $id));
        }, $this->to_array($ids));

        return $this->batchRequest($requests);
    }

    public function deleteFile($id)
    {
        return array_pop($this->deleteFiles($id));
    }

    public function getAnnouncementOptions()
    {
        return [
            'is_rich_text'  => 'true',
            'message_type'  => 'announcement',
        ];
    }

    public function sendMessageToNetworks($ids, $body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
    {
        $ids        = $this->to_array($ids);
        $results    = [];
        foreach($ids as $id)
        {
            $results[$id] = $this->sendMessageToNetwork($id, $body, $title, $og, $tags, $files, $options);
        }
        return $results;
    }

    public function sendMessageToNetwork($id, $body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
    {
        if($id !== null)
        {
            $options['network_id'] = $id;
        }

        return $this->sendMessage($body, $title, $og, $tags, $files, $options);
    }

    public function sendMessageToGroups($ids, $body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
    {
        $ids        = $this->to_array($ids);
        $results    = [];
        foreach($ids as $id)
        {
            $results[$id] = $this->sendMessageToGroup($id, $body, $title, $og, $tags, $files, $options);
        }
        return $results;
    }

    public function sendMessageToGroup($id, $body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
    {
        $options['group_id'] = $id;
        return $this->sendMessage($body, $title, $og, $tags, $files, $options);
    }

    public function sendMessageToUsers($ids, $body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
    {
        return $this->sendMessageToUser($ids, $body, $title, $og, $tags, $files, $options);
    }

    public function sendMessageToUser($id, $body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
    {
        $options['direct_to_user_ids'] = $this->to_array($id);
        return $this->sendMessage($body, $title, $og, $tags, $files, $options);
    }

    public function sendMessageToMessages($ids, $body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
    {
        $ids        = $this->to_array($ids);
        $results    = [];
        foreach($ids as $id)
        {
            $results[$id] = $this->sendMessageToMessage($id, $body, $title, $og, $tags, $files, $options);
        }
        return $results;
    }

    public function sendMessageToMessage($id, $body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
    {
        $options['replied_to_id'] = $id;
        return $this->sendMessage($body, $title, $og, $tags, $files, $options);
    }

    //
    public function sendMessage($body, $title = null, OpenGraph $og = null, $tags = [], $files = [], $options = [])
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

        if($og !== null)
        {
            $data = array_merge($data, array_filter($og->toArray(), function($v) {
                return $v !== null;
            }));
        }

        $data   = array_merge(
            $data,
            $this->array_prefix(array_slice($tags, 0, $length), 'topic'),
            $this->array_prefix(array_values($this->sendFiles(array_slice($files, 0, $length))), 'pending_attachment'),
            $options
        );
        $rs     = $this->post('messages.json', $data)->send();
        print_r($data);

        if($rs->isOk && ($id = ArrayHelper::getValue($rs->data, 'messages.0.id')) !== null)
        {
            if(max(ceil(count($tags) / $length), ceil(count($files) / $length)) > 1)
            {
                $this->sendMessageToMessage(
                    $id,
                    '',
                    null,
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
            Yii::error(sprintf("[%s] failed %d => %s", $rs->headers->get('location'), $rs->statusCode, $rs->content));
            return false;
        }
    }

    public function deleteMessages($ids)
    {
        $requests  = array_map(function ($id) {
            return $this->delete(sprintf("messages/%d", $id));
        }, $this->to_array($ids));

        return $this->batchRequest($requests);
    }

    public function deleteMessage($id)
    {
        return array_pop($this->deleteMessages($id));
    }

    public function batchRequest($requests, $callback = null)
    {
        $responses  = $this->batchSend($requests);

        if($callback === null)
        {
            $callback = function ($rs) {
                if(!$rs->isOk)
                {
                    \Yii::error(sprintf("[%s] failed %d => %s", $rs->headers->get('location'), $rs->statusCode, $rs->content));
                }
                return $rs->isOk;
            };
        }

        return array_map($callback, $responses);
    }
}
