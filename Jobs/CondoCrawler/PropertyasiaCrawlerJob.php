<?php
namespace App\Jobs\CondoCrawler;

use App\Repositories\CondominiumRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Yangqi\Htmldom\Htmldom;

class PropertyasiaCrawlerJob implements BaseCondoCrawlerJob
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $url;

    protected $condominiumRepository;

    /**
     * PropertyasiaCrawlerJob constructor.
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

            $completionYear = null;
            if (isset($condo['available'])) {
                preg_match('/\d{4}/', $condo['available'], $tmp);
                $completionYear = $tmp[0];
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
                    'building_type'   => isset($condo['type']) ? $condo['type'] : null,
                    'latitude'        => isset($condo['latitude']) ? $condo['latitude'] : 0,
                    'longitude'       => isset($condo['longitude']) ? $condo['longitude'] : 0,
                    'completion_year' => $completionYear,
                    'number_floor'    => isset($condo['total_floor']) ? $condo['total_floor'] : null,
                    'number_unit'     => isset($condo['total_units']) ? $condo['total_units'] : null,
                    'facilities'      => isset($condo['facilities']) ? $condo['facilities'] : null,
                    'unit_size'       => isset($condo['unit_types']) ? $condo['unit_types'] : null,
                    'condo_url'       => null,
                    'developer_name'  => isset($condo['developer_name']) ? $condo['developer_name'] : null,
                    'developer_url'   => null,
                    'image_url'       => isset($condo['image_url']) ? $condo['image_url'] : null,
                    'descriptions'    => null,
                    'website_url'     => 'www.propertyasia.ph',
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

        $elems = $dom->find('div.property-list')[0]->find('a[itemprop=url]');

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

        $title         = preg_replace('/\s+/', ' ', $dom->find('h1.title')[0]->plaintext);
        $data['title'] = substr($title, 1, strlen($title) - 2);

        $address         = $dom->find('div.top-info')[0]->find('span.location')[0]->plaintext;
        $data['address'] = substr(preg_replace('/\s+/', ' ', $address), 1, strlen(preg_replace('/\s+/', ' ', $address)) - 2);
        if (count(explode(', ', $data['address'])) == 2) {
            $data['city']     = explode(', ', $data['address'])[0];
            $data['province'] = explode(', ', $data['address'])[1];
        }

        // ! unit_types
        $units = $dom->find('div.top-info')[0]->find('ul.specs')[0]->find('li');
        foreach ($units as $key => $unit) {
            $tmp = explode(':', $unit->plaintext);
            if (count($tmp) == 2) {
                $data[\StringHelper::camel2Snake($tmp[0])] = $tmp[1];
            }
        }

        // type, total_floor, total_units, year_built, available
        $infos = $dom->find('div.listing-info')[1]->find('p');
        foreach ($infos as $info) {
            $property = explode(': ', $info->plaintext);
            if (count($property) == 2) {
                $data[\StringHelper::camel2Snake($property[0])] = $property[1];
            }
        }

        // facilities
        $data['facilities'] = '';
        if (count($dom->find('div.amenity'))) {
            $facilities = $dom->find('div.amenity')[0]->find('ul.list-unstyled')[0]->find('li');
            foreach ($facilities as $key => $facility) {
                $data['facilities'] .= $key ? ",$facility->plaintext" : $facility->plaintext;
            }
        }

        // image
        if (isset($dom->find('div.p-img')[0]) && isset($dom->find('div.p-img')[0]->find('img.img-responsive')[0])) {
            $data['image_url'] = $dom->find('div.p-img')[0]->find('img.img-responsive')[0]->getAttribute('data-src');
        }

        if (isset($dom->find('div[itemprop=aggregateRating]')[0])) {
            $data['developer_name'] = $dom->find('div[itemprop=aggregateRating]')[0]->find('h4.sub-title')[0]->plaintext;
        }

        // map
        $page              = $this->getUrl($url);
        $parsed            = $this->getStringBetween($page, '= new Array(', ');');
        $parsed            = preg_replace('/\s+/', ' ', $parsed);
        $parsed            = substr($parsed, 3, strlen($parsed) - 6);
        $lat               = $this->getStringBetween($parsed, 'Lat:', '],');
        $lng               = $this->getStringBetween($parsed, 'Lng:', '],');
        $data['latitude']  = substr($lat, 3, strlen($lat) - 4);
        $data['longitude'] = substr($lng, 3, strlen($lng) - 4);

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
