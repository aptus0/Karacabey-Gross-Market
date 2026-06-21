<?php

namespace App\Support;

use Illuminate\Support\Str;

class ProductBrandInferer
{
    /** @var array<string, string> */
    private array $aliases = [
        'ABC' => 'ABC',
        'AKINCILAR' => 'Akıncılar',
        'ALLEV' => 'Allev',
        'ANCORA' => 'Ancora',
        'ANKARA' => 'Ankara',
        'ARKO' => 'Arko',
        'AROMA' => 'Aroma',
        'ARTCRAFT' => 'Artcraft',
        'AXE' => 'Axe',
        'AYCA' => 'Ayca',
        'AYDEGER' => 'Aydeğer',
        'AYTAC' => 'Aytaç',
        'BALABAN' => 'Balaban',
        'BANVIT' => 'Banvit',
        'BARAK' => 'Barak',
        'BARONES' => 'Barones',
        'BECEL' => 'Becel',
        'BEBEM' => 'Bebem',
        'BELLUCI' => 'Belluci',
        'BEBELAC' => 'Bebelac',
        'BEBETO' => 'Bebeto',
        'BESTE' => 'Beste',
        'BESLER' => 'Beşler',
        'BIFA' => 'Bifa',
        'BINGO' => 'Bingo',
        'BIRTAT' => 'Birtat',
        'BIZIM MUTFAK' => 'Bizim Mutfak',
        'BIZDEN' => 'Bizden',
        'BLENDAX' => 'Blendax',
        'BOOPS' => 'Boops',
        'BRAVO' => 'Bravo',
        'BREF' => 'Bref',
        'BURCU' => 'Burcu',
        'BURN' => 'Burn',
        'CALVE' => 'Calve',
        'CALDION' => 'Caldion',
        'CAPPY' => 'Cappy',
        'CEMRE MITRA' => 'Cemre Mitra',
        'CIF' => 'Cif',
        'CLEAR' => 'Clear',
        'COLGATE' => 'Colgate',
        'COCA COLA' => 'Coca-Cola',
        'CVS' => 'CVS',
        'CAYKUR' => 'Çaykur',
        'DALIN' => 'Dalin',
        'DALAN' => 'Dalan',
        'DARDANEL' => 'Dardanel',
        'DERMOKIL' => 'Dermokil',
        'DOA' => 'Doa',
        'DOGUS' => 'Doğuş',
        'DOMESTOS' => 'Domestos',
        'DOVE' => 'Dove',
        'DR OETKER' => 'Dr. Oetker',
        'DUNYA' => 'Dünya',
        'DURU' => 'Duru',
        'ECE' => 'Ece',
        'EKER' => 'Eker',
        'EKICI' => 'Ekici',
        'ELIDOR' => 'Elidor',
        'ELSEVE' => 'Elseve',
        'EMILIO' => 'Emilio',
        'EMOTION' => 'Emotion',
        'EMU' => 'Emu',
        'ENDER' => 'Ender',
        'ENERGY' => 'Energy',
        'ERNET' => 'Ernet',
        'ERGUL' => 'Ergül',
        'ERIS' => 'Eriş',
        'ES T' => 'E.S.T',
        'EST' => 'E.S.T',
        'ETI' => 'Eti',
        'FAIRY' => 'Fairy',
        'FAX' => 'Fax',
        'FILIZ' => 'Filiz',
        'FINISH' => 'Finish',
        'FIRINCI' => 'Fırıncı',
        'FIRST' => 'First',
        'FLEXI' => 'Flexi',
        'FLODEX' => 'Flodex',
        'FOLY' => 'Foly',
        'FRITO LAYS' => 'Frito Lay',
        'FRITOLAY' => 'Frito Lay',
        'FUSE TEA' => 'Fuse Tea',
        'FUSE' => 'Fuse Tea',
        'GARNIER' => 'Garnier',
        'GILLETTE' => 'Gillette',
        'GLISS' => 'Gliss',
        'GOLF' => 'Golf',
        'GOLDEN LUX' => 'Golden Lux',
        'GUMUS' => 'Gümüş',
        'HEAD SHOULDERS' => 'Head & Shoulders',
        'HEAD' => 'Head & Shoulders',
        'H SAKIR' => 'Hacı Şakir',
        'HAY HAY' => 'Hay Hay',
        'HES' => 'Hes',
        'HUNKAR' => 'Hünkar',
        'ICIM' => 'İçim',
        'IPEK' => 'İpek',
        'IPANA' => 'İpana',
        'JAGLER' => 'Jagler',
        'K BEY GROSS' => 'K.Bey Gross',
        'KAR MADEN' => 'Kar Maden',
        'KARACA' => 'Karaca',
        'KENT' => 'Kent',
        'KENTON' => 'Kenton',
        'KISMET' => 'Kısmet',
        'KISSED' => 'Kissed',
        'KNORR' => 'Knorr',
        'KOLESTON' => 'Koleston',
        'KOMILI' => 'Komili',
        'KORMADEN' => 'Kormaden',
        'KOYCE' => 'Köyce',
        'LAV' => 'Lav',
        'LEGENA' => 'Legena',
        'LIPTON' => 'Lipton',
        'LOREAL' => "L'Oréal",
        'MAVI' => 'Mavi',
        'MAYLO' => 'Maylo',
        'MEHMET EFENDI' => 'Mehmet Efendi',
        'MOLFIX' => 'Molfix',
        'MOLPED' => 'Molped',
        'MURATBEY' => 'Muratbey',
        'NETKUP' => 'Netküp',
        'NESTLE' => 'Nestle',
        'NIVEA' => 'Nivea',
        'OMO' => 'Omo',
        'OLIPS' => 'Olips',
        'ONCU' => 'Öncü',
        'ONLEM' => 'Önlem',
        'ORAL B' => 'Oral-B',
        'ORKID' => 'Orkid',
        'OZCAN' => 'Özcan',
        'PACI' => 'Paçi',
        'PALETTE' => 'Palette',
        'PALMOLIVE' => 'Palmolive',
        'PANTENE' => 'Pantene',
        'PAPIA' => 'Papia',
        'PAREX' => 'Parex',
        'PARMEX' => 'Parmex',
        'PASABAHCE' => 'Paşabahçe',
        'PEROS' => 'Peros',
        'PERSIL' => 'Persil',
        'PEYMAN' => 'Peyman',
        'PIKNIK' => 'Piknik',
        'PINAR' => 'Pınar',
        'PORCOZ' => 'Porçöz',
        'PRIL' => 'Pril',
        'PRIMA' => 'Prima',
        'PRINGLES' => 'Pringles',
        'PROTEX' => 'Protex',
        'REBUL' => 'Rebul',
        'REXONA' => 'Rexona',
        'SARELLE' => 'Sarelle',
        'SELPAK' => 'Selpak',
        'SESU' => 'Sesu',
        'SHE' => 'She',
        'SLEEPY' => 'Sleepy',
        'SIGMA' => 'Sigma',
        'SOLO' => 'Solo',
        'SOLEN' => 'Şölen',
        'STEPY' => 'Stepy',
        'SUPERFRESH' => 'SuperFresh',
        'SUTAS' => 'Sütaş',
        'TADELLE' => 'Tadelle',
        'TAT' => 'Tat',
        'TEX' => 'Tex',
        'TORKU' => 'Torku',
        'TOYBOX' => 'Toybox',
        'TUKAS' => 'Tukaş',
        'TURSIL' => 'Tursil',
        'ULKER ICIM' => 'İçim',
        'ULKER' => 'Ülker',
        'ULUDAG' => 'Uludağ',
        'UNI' => 'Uni',
        'UNO' => 'Uno',
        'VEET' => 'Veet',
        'VILEDA' => 'Vileda',
        'VIVA CAPPIO' => 'Viva Cappio',
        'VIVA CAPIO' => 'Viva Cappio',
        'WELLA' => 'Wella',
        'YAKUT' => 'Yakut',
    ];

    public function infer(?string $name): ?string
    {
        $normalized = $this->normalizeForMatch($name);
        if ($normalized === '') {
            return null;
        }

        $aliases = $this->aliases;
        uksort($aliases, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($aliases as $prefix => $brand) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix.' ')) {
                return $brand;
            }
        }

        return null;
    }

    public function normalizeBrand(?string $brand): ?string
    {
        $brand = trim((string) $brand);
        if ($brand === '') {
            return null;
        }

        return $this->aliases[$this->normalizeForMatch($brand)] ?? $this->titleFromOriginal($brand);
    }

    private function normalizeForMatch(?string $value): string
    {
        $value = Str::ascii(trim((string) $value));
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function titleFromOriginal(string $value): string
    {
        $lower = strtr($value, [
            'I' => 'ı',
            'İ' => 'i',
        ]);
        $lower = mb_strtolower($lower, 'UTF-8');

        return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    }
}
