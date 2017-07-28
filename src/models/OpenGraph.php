<?php

namespace macfly\yammer\models;

use yii\base\Model;

class OpenGraph extends Model
{
    public $og_url;
    public $og_fetch        = true;
    public $og_title        = null;
    public $og_site_name    = null;
    public $og_object_type  = null;
    public $og_description  = null;
    public $og_image        = null;
    public $og_meta         = null;
    public $og_private      = true;
}
