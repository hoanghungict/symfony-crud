<?php
namespace App\Jobs\CondoCrawler;

use App\Repositories\CondominiumRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Yangqi\Htmldom\Htmldom;

class PhrealestateCrawlerJob implements BaseCondoCrawlerJob
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $url;

    protected $condominiumRepository;

    /**
     * PhrealestateCrawlerJob constructor.
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
                    'title'           => isset($condo['title']) ? $condo['title'] : null,
                    'postal_code'     => null,
                    'country'         => 'Philippines',
                    'country_code'    => 'PHL',
                    'province'        => isset($condo['province']) ? $condo['province'] : null,
                    'city'            => isset($condo['city']) ? $condo['city'] : null,
                    'address'         => (isset($condo['address']) ? $condo['address'] : null).((isset($condo['address']) && isset($condo['address'])) ? ', ' : '').(isset($condo['location']) ? $condo['location'] : null),
                    'building_type'   => isset($condo['project_type']) ? $condo['project_type'] : null,
                    'latitude'        => 0,
                    'longitude'       => 0,
                    'completion_year' => null,
                    'number_floor'    => null,
                    'number_unit'     => null,
                    'developer_name'  => null,
                    'facilities'      => null,
                    'unit_size'       => isset($condo['units']) ? $condo['units'] : null,
                    'condo_url'       => isset($condo['condos_url']) ? $condo['condos_url'] : null,
                    'developer_url'   => null,
                    'image_url'       => isset($condo['image_url']) ? $condo['image_url'] : null,
                    'descriptions'    => isset($condo['descriptions']) ? $condo['descriptions'] : null,
                    'website_url'     => 'www.phrealestate.com',
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
        $dom   = new Htmldom($url);
        $elems = $dom->find('div.quick-overview a[itemprop=url]');

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

        $elems = $dom->find('div.media-body');
        $elems = str_replace("\t", '', $elems[0]->plaintext);

        $title           = $dom->find('h3 a[itemprop=url]');
        $condos['title'] = substr($title[0]->plaintext, 0, strlen($title[0]->plaintext));

        $descriptions           = $dom->find('p[style=text-align: justify;]');
        $condos['descriptions'] = isset($descriptions[0]->plaintext) ? substr($descriptions[0]->plaintext, 3, strlen($descriptions[0]->plaintext) - 9) : null;

        $condos['image_url'] = $dom->find('img[itemprop=image]')[0]->src;
        foreach (explode("\r\n", $elems) as $key => $condo) {
            $property = explode(':', $condo);
            if (count($property) == 2) {
                $condos[\StringHelper::camel2Snake($property[0])] = substr(preg_replace('!\s+!', ' ', $property[1]), 1, strlen(preg_replace('!\s+!', ' ', $property[1])) - 2);
            } else {
                $condos['condos_url'] = substr(preg_replace('!\s+!', ' ', $property[0]), 1, strlen(preg_replace('!\s+!', ' ', $property[0])) - 7);
            }
        }
        $condos['province'] = $dom->find('div.media-body')[0]->find('span[itemprop=addressRegion]')[0]->plaintext;
        $condos['province'] = substr(preg_replace('!\s+!', ' ', $condos['province']), 1, strlen($condos['province']) - 3);
        $condos['city']     = $dom->find('div.media-body')[0]->find('span[itemprop=addressLocality]')[0]->plaintext;
        $condos['city']     = substr(preg_replace('!\s+!', ' ', $condos['city']), 1, strlen($condos['city']) - 3);

        $condos['location'] = $condos['city'].', '.$condos['province'];

        return $condos;
    }
}
