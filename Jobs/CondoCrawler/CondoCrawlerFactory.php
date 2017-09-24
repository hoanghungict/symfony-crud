<?php
namespace App\Jobs\CondoCrawler;

use App\Repositories\CondominiumRepositoryInterface;

class CondoCrawlerFactory
{
    /** @var \App\Repositories\CondominiumRepositoryInterface */
    protected $condominiumRepository;

    public function __construct(
        CondominiumRepositoryInterface  $condominiumRepository
    ) {
        $this->condominiumRepository    = $condominiumRepository;
    }

    public function crawl($pageUrl)
    {
        switch (parse_url($pageUrl, PHP_URL_HOST)) {
            case 'www.propertyasia.ph':
                $crawler = new \App\Jobs\CondoCrawler\PropertyasiaCrawlerJob($pageUrl, $this->condominiumRepository);
                break;
            case 'www.zipmatch.com':
                $crawler = new \App\Jobs\CondoCrawler\ZipmatchCrawlerJob($pageUrl, $this->condominiumRepository);
                break;
            case 'www.phrealestate.com':
                $crawler = new \App\Jobs\CondoCrawler\PhrealestateCrawlerJob($pageUrl, $this->condominiumRepository);
                break;
            case 'preselling.com.ph':
                $crawler = new \App\Jobs\CondoCrawler\PresellingCrawlerJob($pageUrl, $this->condominiumRepository);
                break;
            case 'philpropertyexpert.com':
                $crawler = new \App\Jobs\CondoCrawler\PhilpropertyexpertCrawlerJob($pageUrl, $this->condominiumRepository);
                break;
            case 'avidaland.com':
                $crawler = new \App\Jobs\CondoCrawler\AvidalandCrawlerJob($pageUrl, $this->condominiumRepository);
                break;
            default:
                $crawler = false;
                break;
        }

        return $crawler;
    }
}
