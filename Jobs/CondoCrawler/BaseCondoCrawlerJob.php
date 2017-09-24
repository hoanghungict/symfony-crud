<?php
namespace App\Jobs\CondoCrawler;

interface BaseCondoCrawlerJob
{
    /**
     * Get list url of all condos in page.
     *
     * @return array
     */
    public function getListCondosUrl($url);

    /**
     * Get condos info.
     *
     * @return array
     */
    public function getDetailCondo($url);
}
