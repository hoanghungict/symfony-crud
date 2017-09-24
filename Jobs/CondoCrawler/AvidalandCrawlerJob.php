<?php
namespace App\Jobs\CondoCrawler;

use App\Repositories\CondominiumRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Yangqi\Htmldom\Htmldom;

class AvidalandCrawlerJob implements BaseCondoCrawlerJob
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $url;

    protected $condominiumRepository;

    /**
     * AvidalandCrawlerJob constructor.
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
                    'building_type'   => 'Condominium',
                    'latitude'        => isset($condo['latitude']) ? $condo['latitude'] : 0,
                    'longitude'       => isset($condo['longitude']) ? $condo['longitude'] : 0,
                    'completion_year' => isset($condo['complete_year']) ? $condo['complete_year'] : null,
                    'number_floor'    => null,
                    'number_unit'     => null,
                    'facilities'      => null,
                    'unit_size'       => isset($condo['unit_sizes']) ? $condo['unit_sizes'] : null,
                    'condo_url'       => null,
                    'developer_name'  => null,
                    'developer_url'   => null,
                    'image_url'       => isset($condo['image_url']) ? $condo['image_url'] : null,
                    'descriptions'    => null,
                    'website_url'     => 'avidaland.com',
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

        $elems = $dom->find('h3.property-project-name');

        $urls = [];
        foreach ($elems as $elem) {
            $urls[] = 'http://avidaland.com/'.$elem->find('a')[0]->href;
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

        $data['title'] = $dom->find('div#projectConcept')[0]->find('h1')[0]->plaintext;

        // location, unit_sizes, price_range, status, complete_year
        $infos = $dom->find('div#projectConcept')[0]->find('div.col-5');
        foreach ($infos as $info) {
            $tmp = explode(':', $info->plaintext);
            if (count($tmp) == 2) {
                $field                                     = preg_replace('/\s+/', ' ', $tmp[1]);
                $data[\StringHelper::camel2Snake($tmp[0])] = substr($field, 1, strlen($field) - 2);
            } elseif (count($tmp) == 1) {
                $complateYear          = preg_replace('/\s+/', ' ', $info->plaintext);
                $data['complete_year'] = substr($complateYear, 17, strlen($complateYear) - 18);
            }
        }

        // map
        $data['latitude']  = $dom->find('div#map_canvas')[0]->getAttribute('lat');
        $data['longitude'] = $dom->find('div#map_canvas')[0]->getAttribute('long');
        $data['image_url'] = 'http://avidaland.com/'.$dom->find('img.project-banner-img')[0]->src;

        return $data;
    }
}
