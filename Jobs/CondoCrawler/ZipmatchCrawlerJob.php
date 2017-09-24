<?php
namespace App\Jobs\CondoCrawler;

use App\Repositories\CondominiumRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Yangqi\Htmldom\Htmldom;

class ZipmatchCrawlerJob implements BaseCondoCrawlerJob
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $url;

    protected $condominiumRepository;

    /**
     * ZipmatchCrawlerJob constructor.
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
                    'building_type'   => isset($condo['project_type']) ? $condo['project_type'] : 'condominium',
                    'latitude'        => isset($condo['latitude']) ? $condo['latitude'] : 0,
                    'longitude'       => isset($condo['longitude']) ? $condo['longitude'] : 0,
                    'completion_year' => isset($condo['year_built']) ? $condo['year_built'] : (isset($condo['turnover_date']) ? $condo['turnover_date'] : null),
                    'number_floor'    => isset($condo['floors']) ? $condo['floors'] : null,
                    'number_unit'     => isset($condo['total_units']) ? $condo['total_units'] : null,
                    'facilities'      => isset($condo['facilities']) ? $condo['facilities'] : null,
                    'unit_size'       => null,
                    'condo_url'       => null,
                    'developer_name'  => isset($condo['developer']) ? $condo['developer'] : null,
                    'developer_url'   => null,
                    'image_url'       => isset($condo['image_url']) ? $condo['image_url'] : null,
                    'descriptions'    => null,
                    'website_url'     => 'www.zipmatch.com',
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

        $elems = $dom->find('p.zm-font-size-m');

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

        $data['title']    = $dom->find('h1[itemprop=name]')[0]->plaintext;
        $data['province'] = $dom->find('span[itemprop=addressRegion]')[0]->plaintext;
        $data['city']     = $dom->find('span[itemprop=addressLocality]')[0]->plaintext;
        if (isset($dom->find('span[itemprop=streetAddress]')[0])) {
            $data['address']  = $dom->find('span[itemprop=streetAddress]')[0]->plaintext.', '.$data['city'].', '.$data['province'];
        } else {
            $data['address']  = $data['city'].', '.$data['province'];
        }

        $data['latitude']   = $dom->find('meta[itemprop=latitude]')[0]->getAttribute('content');
        $data['longitude']  = $dom->find('meta[itemprop=longitude]')[0]->getAttribute('content');

        // status, elevators, year_built, floors
        if (count($dom->find('div#buildings'))) {
            if (isset($dom->find('div.building-details')[0])) {
                $infos = $dom->find('div.building-details')[0]->find('div.project-info')[0]->find('div.popover-body')[0]->find('li');
                foreach ($infos as $info) {
                    $tmp                                       = explode(' - ', $info->plaintext);
                    $data[\StringHelper::camel2Snake($tmp[0])] = $tmp[1];
                }
            }
        } else {
            if (isset($dom->find('div.building-details')[0])) {
                $infos = $dom->find('div.building-details')[0]->find('div.project-info')[0]->find('span');
                for ($i = 0; $i < count($infos); $i += 2) {
                    $data[\StringHelper::camel2Snake($infos[$i]->plaintext)] = $infos[$i + 1]->plaintext;
                }
            }
        }

        // project_status, turnover_year, developer, project_type, unit_types
        $keys   = $dom->find('table.details-table')[0]->find('span.cell-title');
        $values = $dom->find('table.details-table')[0]->find('b.cell-value');
        foreach ($keys as $i => $key) {
            $data[\StringHelper::camel2Snake($key->plaintext)] = $values[$i]->plaintext;
        }

        $data['facilities'] = '';
        if (isset($dom->find('div.feature-amenities')[0])) {
            $facilities = $dom->find('div.feature-amenities')[0]->find('span.item-feature');
            foreach ($facilities as $key => $facility) {
                $data['facilities'] .= $key ? ', '.$facility->plaintext : $facility->plaintext;
            }
        }

        $data['image_url']  = $this->getStringBetween($dom->find('section.intro')[0]->getAttribute('style'), "url('", "');");

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
}
