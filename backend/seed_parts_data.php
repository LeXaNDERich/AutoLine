<?php
declare(strict_types=1);

function seedPriceMultipliers(): array
{
    return [
        'audi' => 1.35, 'avatr' => 1.4, 'baw' => 1.15, 'bmw' => 1.55, 'byd' => 1.18,
        'changan' => 1.18, 'chery' => 1.15, 'dongfeng' => 1.16, 'ford' => 1.1,
        'geely' => 1.15, 'genesis' => 1.45, 'gmc' => 1.6, 'honda' => 1.25,
        'hongqi' => 1.42, 'hyundai' => 1.15, 'infiniti' => 1.5, 'jeep' => 1.35,
        'kia' => 1.12, 'lada' => 1.0, 'land-rover' => 1.7, 'lexus' => 1.6,
        'lixiang' => 1.5, 'mazda' => 1.2, 'mercedes' => 1.65, 'mini' => 1.35,
        'mitsubishi' => 1.15, 'nissan' => 1.15, 'polar-stone' => 1.38, 'porsche' => 1.9,
        'skoda' => 1.15, 'subaru' => 1.25, 'tank' => 1.28, 'toyota' => 1.2,
        'volkswagen' => 1.2, 'volvo' => 1.55, 'voyah' => 1.45, 'xiaomi' => 1.35,
        'zeekr' => 1.45, 'zhiji' => 1.4,
    ];
}

function seedModelPresets(): array
{
    return [
        'bmw' => ['3 Series', '5 Series', '7 Series', 'X1', 'X3', 'X5', 'X6', 'X7', 'M3', 'M5', 'i4', 'iX'],
        'mercedes' => ['A-Class', 'C-Class', 'E-Class', 'S-Class', 'GLA', 'GLC', 'GLE', 'GLS', 'AMG C 63', 'AMG GT', 'EQE', 'EQS'],
        'audi' => ['A3', 'A4', 'A6', 'A8', 'Q3', 'Q5', 'Q7', 'Q8', 'S5', 'RS6', 'e-tron', 'Q4 e-tron'],
        'toyota' => ['Corolla', 'Camry', 'RAV4', 'Highlander', 'Land Cruiser', 'Prado', 'Yaris', 'C-HR', 'Hilux', 'Supra', 'Prius', 'Crown'],
        'kia' => ['Rio', 'Ceed', 'Cerato', 'K5', 'Sportage', 'Sorento', 'Seltos', 'Stinger', 'Carnival', 'EV6', 'Mohave', 'Picanto'],
        'hyundai' => ['Solaris', 'Elantra', 'Sonata', 'Tucson', 'Santa Fe', 'Palisade', 'Creta', 'Kona', 'i30', 'Staria', 'IONIQ 5', 'IONIQ 6'],
        'volkswagen' => ['Polo', 'Jetta', 'Passat', 'Tiguan', 'Touareg', 'Golf', 'Taos', 'T-Roc', 'Arteon', 'ID.4', 'Amarok', 'Multivan'],
        'lada' => ['Granta', 'Vesta', 'Niva Legend', 'Niva Travel', 'Largus', 'XRAY', 'Vesta SW', 'Vesta Cross', 'Kalina', 'Priora', 'Samara', 'Niva Sport'],
        'ford' => ['Focus', 'Mondeo', 'Kuga', 'Explorer', 'Mustang', 'F-150', 'Puma', 'EcoSport', 'Transit', 'Ranger', 'Fusion', 'Edge'],
        'honda' => ['Civic', 'Accord', 'CR-V', 'HR-V', 'Pilot', 'Fit', 'Odyssey', 'City', 'Jazz', 'ZR-V', 'e:Ny1', 'Passport'],
        'nissan' => ['Qashqai', 'X-Trail', 'Murano', 'Juke', 'Sentra', 'Altima', 'Pathfinder', 'Patrol', 'Leaf', 'Tiida', 'Terrano', 'Note'],
        'mazda' => ['Mazda3', 'Mazda6', 'CX-3', 'CX-30', 'CX-5', 'CX-60', 'CX-9', 'MX-5', 'BT-50', 'CX-50', 'CX-90', 'Demio'],
        'lexus' => ['ES', 'IS', 'LS', 'NX', 'RX', 'UX', 'GX', 'LX', 'LC', 'RC', 'RZ', 'LM'],
        'porsche' => ['911', 'Cayenne', 'Macan', 'Panamera', 'Taycan', 'Boxster', 'Cayman', '718', 'Carrera', 'Turbo S', 'Macan GTS', 'Cayenne Coupe'],
        'volvo' => ['S60', 'S90', 'V60', 'V90', 'XC40', 'XC60', 'XC90', 'C40', 'EX30', 'EX90', 'V40', 'S40'],
        'land-rover' => ['Defender', 'Discovery', 'Discovery Sport', 'Range Rover', 'Range Rover Sport', 'Range Rover Velar', 'Range Rover Evoque', 'Freelander', 'LR4', 'LR3', 'LR2', 'Series III'],
        'jeep' => ['Wrangler', 'Grand Cherokee', 'Cherokee', 'Compass', 'Renegade', 'Gladiator', 'Commander', 'Patriot', 'Liberty', 'Wagoneer', 'Avenger', 'Grand Wagoneer'],
        'mitsubishi' => ['Outlander', 'Pajero', 'Pajero Sport', 'ASX', 'L200', 'Eclipse Cross', 'Lancer', 'Galant', 'Colt', 'Mirage', 'Montero', 'Delica'],
        'subaru' => ['Forester', 'Outback', 'XV', 'Impreza', 'Legacy', 'WRX', 'BRZ', 'Ascent', 'Crosstrek', 'Levorg', 'Tribeca', 'Solterra'],
        'skoda' => ['Octavia', 'Rapid', 'Superb', 'Kodiaq', 'Karoq', 'Kamiq', 'Fabia', 'Scala', 'Enyaq', 'Yeti', 'Roomster', 'Citigo'],
        'genesis' => ['G70', 'G80', 'G90', 'GV60', 'GV70', 'GV80', 'Electrified G80', 'Electrified GV70', 'Coupe', 'Essential', 'Sport', 'Luxury'],
        'byd' => ['Atto 3', 'Han', 'Tang', 'Song Plus', 'Seal', 'Dolphin', 'Yuan Plus', 'Qin Plus', 'Destroyer 05', 'Seagull', 'Frigate 07', 'Song Pro'],
        'chery' => ['Tiggo 4', 'Tiggo 7', 'Tiggo 8', 'Arrizo 8', 'Omoda 5', 'Exeed TXL', 'Exeed VX', 'Tiggo 2', 'Tiggo 3', 'Bonus', 'Very', 'IndiS'],
    ];
}

function seedGenericModelSuffixes(): array
{
    return ['City', 'Sedan', 'Comfort', 'Business', 'Crossover', 'SUV', 'Sport', 'Premium', 'Touring', 'Executive', 'Electric', 'Performance'];
}

function seedPartTemplates(): array
{
    return [
        ['title' => 'Тормозные колодки передние', 'desc' => 'Подбор по VIN, проверка суппортов и направляющих.', 'meta' => 'Срок: 1-2 дня', 'base' => 1950],
        ['title' => 'Тормозные колодки задние', 'desc' => 'Комплект с осмотром тормозного контура.', 'meta' => 'Срок: 1-2 дня', 'base' => 1750],
        ['title' => 'Диск тормозной', 'desc' => 'Проверка биения и толщины, установка с колодками.', 'meta' => 'Срок: 2-3 дня', 'base' => 3200],
        ['title' => 'Воздушный фильтр', 'desc' => 'Чистая тяга и стабильная работа двигателя.', 'meta' => 'Срок: 1 день', 'base' => 650],
        ['title' => 'Масляный фильтр', 'desc' => 'Подбор по мотору, совместим с плановым ТО.', 'meta' => 'Срок: 1 день', 'base' => 520],
        ['title' => 'Фильтр салона угольный', 'desc' => 'Комфорт в салоне и защита системы вентиляции.', 'meta' => 'Срок: сегодня', 'base' => 920],
        ['title' => 'Свечи зажигания', 'desc' => 'Ровный холостой ход и стабильный запуск.', 'meta' => 'Срок: 1-2 дня', 'base' => 1250],
        ['title' => 'Амортизатор передний', 'desc' => 'Установка с проверкой опор и развал-схождения.', 'meta' => 'Срок: 2-5 дней', 'base' => 5200],
        ['title' => 'Амортизатор задний', 'desc' => 'Плавный ход и контроль кузовных креплений.', 'meta' => 'Срок: 2-5 дней', 'base' => 4600],
        ['title' => 'Стойка стабилизатора', 'desc' => 'Устранение стуков и люфтов подвески.', 'meta' => 'Срок: 1-2 дня', 'base' => 1750],
        ['title' => 'Рычаг подвески', 'desc' => 'Замена с контролем геометрии и люфтов.', 'meta' => 'Срок: 3-5 дней', 'base' => 4950],
        ['title' => 'Ремень ГРМ', 'desc' => 'Комплект с роликами и проверкой помпы.', 'meta' => 'Срок: 3-5 дней', 'base' => 6800],
        ['title' => 'Ремень генератора', 'desc' => 'Без скрипа и с корректной зарядкой АКБ.', 'meta' => 'Срок: 1-3 дня', 'base' => 1750],
        ['title' => 'Термостат', 'desc' => 'Стабильный прогрев и рабочая температура мотора.', 'meta' => 'Срок: 1-2 дня', 'base' => 2300],
        ['title' => 'Датчик ABS', 'desc' => 'Проверка сигнала и диагностика блока.', 'meta' => 'Срок: 2-4 дня', 'base' => 3800],
        ['title' => 'Сайлентблок рычага', 'desc' => 'Точная геометрия и тишина в подвеске.', 'meta' => 'Срок: 2-3 дня', 'base' => 1450],
    ];
}

function seedFormatPrice(int $amount): string
{
    return 'от ' . number_format($amount, 0, ',', ' ') . ' ₽';
}

function seedPartImageKeyword(string $title): string
{
    $map = [
        'колодк' => 'brake,pads,car',
        'диск' => 'brake,disc,car',
        'фильтр' => 'air,filter,car',
        'свеч' => 'spark,plug,engine',
        'амортизатор' => 'shock,absorber,car',
        'стойка' => 'suspension,car,parts',
        'рычаг' => 'car,suspension,service',
        'ремень' => 'engine,belt,car',
        'термостат' => 'engine,cooling,car',
        'датчик' => 'abs,sensor,car',
        'сайлентблок' => 'suspension,bush,car',
    ];
    $lower = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
    foreach ($map as $needle => $keyword) {
        $pos = function_exists('mb_strpos') ? mb_strpos($lower, $needle, 0, 'UTF-8') : strpos($lower, $needle);
        if ($pos !== false) {
            return $keyword;
        }
    }
    return 'car,parts,service';
}

function seedModelsForBrand(string $brandSlug, string $brandName): array
{
    $presets = seedModelPresets();
    if (isset($presets[$brandSlug])) {
        return $presets[$brandSlug];
    }
    $models = [];
    foreach (seedGenericModelSuffixes() as $suffix) {
        $models[] = $brandName . ' ' . $suffix;
    }
    return $models;
}

function seedLoadBrands(): array
{
    $path = dirname(__DIR__) . '/assets/data/brands.json';
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['brands']) || !is_array($decoded['brands'])) {
        return [];
    }
    return $decoded['brands'];
}
