<?php
namespace App\Jobs\CondoCrawler;

use App\Repositories\CondominiumRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Yangqi\Htmldom\Htmldom;

class PresellingCrawlerJob implements BaseCondoCrawlerJob
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $url;

    protected $condominiumRepository;

    /**
     * PresellingCrawlerJob constructor.
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
                    'province'        => isset($condo['province']) ? $condo['province'] : null,
                    'city'            => isset($condo['city']) ? $condo['city'] : null,
                    'address'         => isset($condo['address']) ? $condo['address'] : null,
                    'building_type'   => 'condominium',
                    'latitude'        => isset($condo['latitude']) ? $condo['latitude'] : 0,
                    'longitude'       => isset($condo['longitude']) ? $condo['longitude'] : 0,
                    'completion_year' => isset($condo['turnover_date']) ? $condo['turnover_date'] : null,
                    'number_floor'    => isset($condo['total_floor']) ? $condo['total_floor'] : null,
                    'number_unit'     => isset($condo['total_units']) ? $condo['total_units'] : null,
                    'facilities'      => isset($condo['facilities']) ? $condo['facilities'] : null,
                    'unit_size'       => isset($condo['unit_types']) ? $condo['unit_types'] : null,
                    'condo_url'       => null,
                    'developer_name'  => isset($condo['developer']) ? $condo['developer'] : (isset($condo['developer_name']) ? $condo['developer_name'] : null),
                    'developer_url'   => null,
                    'image_url'       => isset($condo['image_url']) ? $condo['image_url'] : null,
                    'descriptions'    => null,
                    'website_url'     => 'preselling.com.ph',
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

        $elems = $dom->find('figure');

        $urls = [];
        foreach ($elems as $elem) {
            $urls[] = $elem->find('a')[0]->href;
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

        $data['title'] = $dom->find('h1.page-title')[0]->plaintext;
        if (isset($dom->find('div.agent-detail')[0])) {
            $data['developer_name'] = $dom->find('div.agent-detail')[0]->find('h3')[0]->plaintext;
        }

        // address, developer, available_units, turnover_date/turnover_date_tower_1
        if (isset($dom->find('ul.additional-details')[0])) {
            $infos = $dom->find('ul.additional-details')[0]->find('li');
            foreach ($infos as $info) {
                $info                                       = explode(': ', preg_replace('/\s+/', ' ', $info->plaintext));
                $data[\StringHelper::camel2Snake($info[0])] = $info[1];
            }
        }

        // map
        $page              = $this->getUrl($url);
        $parsed            = $this->getStringBetween($page, 'propertyMarkerInfo =', '}');
        $data['latitude']  = $this->getStringBetween($parsed, '"lat":"', '",');
        $data['longitude'] = $this->getStringBetween($parsed, '"lang":"', '",');

        $data['facilities'] = '';
        if (isset($dom->find('ul.arrow-bullet-list')[0])) {
            $facilities = $dom->find('ul.arrow-bullet-list')[0]->find('li');
            foreach ($facilities as $key => $facility) {
                $data['facilities'] .= $key ? ', '.$facility->plaintext : $facility->plaintext;
            }
        }

        $data['province'] = $dom->find('nav.property-breadcrumbs')[0]->find('li')[1]->plaintext;
        if (isset($data['address']) && count(explode(', ', $data['address'])) == 2) {
            $data['city'] = explode(', ', $data['address'])[1];
        }

        $data['image_url'] = $dom->find('div#property-featured-image')[0]->find('img')[0]->src;

        return $data;
    }

    private function getStringBetween($string, $start, $end)
    {
        $string = ' '.$string;
        $ini    = strpos($string, $start);
        if ($ini == 0) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;

        return substr($string, $ini, $len);
    }

    private function getUrl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }
}
