<?php
namespace App\Jobs\CondoCrawler;

use App\Repositories\CondominiumRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Yangqi\Htmldom\Htmldom;

class PhilpropertyexpertCrawlerJob implements BaseCondoCrawlerJob
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $url;

    protected $condominiumRepository;

    /**
     * PhilpropertyexpertCrawlerJob constructor.
     *
     * @param $url
     * @param CondominiumRepositoryInterface $condominiumRepository
     */
    public function __construct(
        $url,
        CondominiumRepositoryInterface $condominiumRepository
    ) {
        $this->url                     = $url;
        $this->condominiumRepository   = $condominiumRepository;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $urls = $this->getListCondosUrl($this->url);
        if (!$urls) {
            return false;
        }

        $count = 0;
        foreach ($urls as $url) {
            $condo = $this->getDetailCondo($url);
            if (!$condo) {
                continue;
            }

            $check = $this->condominiumRepository->findByTitle($condo['title']);
            if (count($check)) {
                continue;
            }

            $count += 1;
            $this->condominiumRepository->create(
                [
                    'title'           => isset($condo['title']) ? $condo['title'] : 'null',
                    'postal_code'     => null,
                    'country'         => 'Philippines',
                    'country_code'    => 'PHL',
                    'province'        => null,
                    'city'            => null,
                    'address'         => isset($condo['location']) ? $condo['location'] : null,
                    'building_type'   => isset($condo['property_type']) ? $condo['property_type'] : null,
                    'latitude'        => 0,
                    'longitude'       => 0,
                    'completion_year' => isset($condo['turnover_built']) ? $condo['turnover_built'] : null,
                    'number_floor'    => null,
                    'number_unit'     => null,
                    'developer_name'  => isset($condo['developer']) ? $condo['developer'] : null,
                    'facilities'      => isset($condo['parking']) ? 'parking: '.$condo['parking'] : null,
                    'unit_size'       => (isset($condo['bedroom']) ? 'bedroom: '.$condo['bedroom'] : null).(isset($condo['bathroom']) ? ' | bathroom: '.$condo['bathroom'] : null),
                    'condo_url'       => null,
                    'developer_url'   => null,
                    'image_url'       => isset($condo['image_url']) ? $condo['image_url'] : null,
                    'descriptions'    => null,
                    'website_url'     => 'philpropertyexpert.com',
                    'original_url'    => $url,
                ]
            );
        }

        return $count;
    }

    /**
     * Get list url of all condos in page.
     *
     * @return array
     */
    public function getListCondosUrl($url)
    {
        try {
            $dom = new Htmldom($url);
        } catch (\Exception $e) {
            return;
        }

        $elems = $dom->find('a.overlay');

        $urls = [];
        foreach ($elems as $elem) {
            $urls[] = $elem->href;
        }

        return array_unique($urls);
    }

    /**
     * Get condos info.
     *
     * @return array
     */
    public function getDetailCondo($url)
    {
        try {
            $dom = new Htmldom($url);
        } catch (\Exception $e) {
            return;
        }

        $title           = $dom->find('h2.prop-title');
        $condos['title'] = $title[0]->plaintext;

        $condos['image_url'] = isset($dom->find('img.media-object')[0]) ? $dom->find('img.media-object')[0]->src : null;

        $elems = $dom->find('li.info-label');
        foreach ($elems as $elem) {
            $property                                         = explode(':', $elem->plaintext);
            $condos[\StringHelper::camel2Snake($property[0])] = substr(preg_replace('/\s+/', ' ', $property[1]), 1, strlen(preg_replace('/\s+/', ' ', $property[1])) - 2);
        }

        return $condos;
    }
}
