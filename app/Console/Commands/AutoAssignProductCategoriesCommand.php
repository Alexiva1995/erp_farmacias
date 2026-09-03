<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoAssignProductCategoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:auto-assign-categories {--force : Sobrescribe categorías existentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clasifica y asigna automáticamente cada producto a su categoría correspondiente de farmacia / e-commerce';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $categories = \App\Models\Category::all()->keyBy('name');
        if ($categories->isEmpty()) {
            $this->error('No hay categorías creadas. Ejecuta primero categories:renew-ecommerce.');
            return 1;
        }

        $rules = [
            'Cardiovascular e Hipertensión' => [
                'losartan', 'enalapril', 'captopril', 'valsartan', 'amlodipino', 'amlodipina', 'carvedilol',
                'atenolol', 'bisoprolol', 'metoprolol', 'hidroclorotiazida', 'furosemida', 'espironolactona',
                'atorvastatina', 'rosuvastatina', 'simvastatina', 'gemfibrozilo', 'clopidogrel', 'diltiazem',
                'verapamilo', 'digoxina', 'irbesartan', 'candesartan', 'telmisartan', 'nebivolol', 'nifedipina',
                'isosorbide', 'nitroglicerina', 'antihipertensivo', 'presion', 'corazon', 'cardio'
            ],
            'Diabetes y Endocrinología' => [
                'metformina', 'glibenclamida', 'glimepirida', 'insulina', 'levotiroxina', 'eutirox',
                'dapagliflozina', 'empagliflozina', 'sitagliptina', 'vildagliptina', 'linagliptina',
                'glucofage', 'galvus', 'januvia', 'forxiga', 'jardiance', 'glucosa', 'glucometro',
                'tiras reactivas', 'lancetas', 'diabetico', 'tiroides'
            ],
            'Gastrointestinal y Digestivo' => [
                'omeprazol', 'pantoprazol', 'esomeprazol', 'lansoprazol', 'ranitidina', 'famotidina',
                'hidroxido de aluminio', 'milpax', 'maalox', 'antiacido', 'metoclopramida', 'domperidona', 'domperidone',
                'ondansetron', 'dimeticona', 'simeticona', 'flato', 'gases', 'enterogermina', 'probiotico',
                'loperamida', 'trimebutina', 'butilhioscina', 'buscapina', 'bromuro de pinaverio',
                'polietilenglicol', 'lactulosa', 'bisacodilo', 'senokot', 'laxante', 'digestivo',
                'higado', 'gastrico', 'reflujo', 'acidez', 'suero oral', 'sales de rehidratacion', 'pedialyte', 'hidrafix',
                'suerolito', 'electrolitica', 'electrolitos', 'prolardii', 'saccharomyces', 'lactobacillus', 'lactovac',
                'tonum', 'enterol', 'florestor', 'liolactil', 'pankreoflat', 'creon', 'enzimas'
            ],
            'Dolor, Inflamación y Fiebre' => [
                'acetaminofen', 'paracetamol', 'ibuprofeno', 'diclofenac', 'diclofenaco', 'ketoprofeno',
                'naproxeno', 'meloxicam', 'piroxicam', 'celecoxib', 'etoricoxib', 'tramadol', 'ketorolac',
                'ketorolaco', 'clonixinato', 'dorixina', 'aspirina', 'acido acetilsalicilico', 'dipirona',
                'metamizol', 'dolor', 'analgesico', 'antiinflamatorio', 'relajante muscular', 'tiocolchicosido',
                'ciclobenzaprina', 'pridinol', 'dorflex', 'tizanidina', 'artritis', 'migraña', 'teragrip'
            ],
            'Respiratorio y Gripe' => [
                'loratadina', 'cetirizina', 'desloratadina', 'levocetirizina', 'fexofenadina', 'clorfeniramina',
                'salbutamol', 'budesonida', 'beclometasona', 'bromhexina', 'ambroxol', 'acetilcisteina',
                'antiflu', 'antigripal', 'descongestionante', 'oximetazolina', 'nafazolina', 'pseudoefedrina',
                'dextrometorfano', 'codeina', 'jarabe tos', 'tos', 'gripe', 'asma', 'inhalador', 'nebulizador',
                'pulmonar', 'rinomer', 'marimer', 'agua de mar', 'afrin', 'clenbuterol', 'clenbunal', 'oxolamina',
                'flemibar', 'bisolvon', 'mucosan', 'aerovan', 'berodual', 'atrovent'
            ],
            'Antibióticos y Antivirales' => [
                'amoxicilina', 'ampicilina', 'azitromicina', 'claritromicina', 'eritromicina', 'ciprofloxacina',
                'levofloxacina', 'cefalexina', 'cefadroxilo', 'cefixima', 'ceftriaxona', 'clindamicina',
                'metronidazol', 'doxiciclina', 'trimetoprim', 'sulfametoxazol', 'bactrim', 'aciclovir',
                'valaciclovir', 'antiviral', 'antibiotico', 'amoxil', 'augmentin', 'curam', 'fulgram',
                'ciprolet', 'unastin', 'sultamicilina', 'nitrofurantoina', 'fosfomicina'
            ],
            'Salud Mental y Sistema Nervioso' => [
                'alprazolam', 'clonazepam', 'diazepam', 'lorazepam', 'bromazepam', 'sertralina', 'fluoxetina',
                'escitalopram', 'paroxetina', 'venlafaxina', 'duloxetina', 'quetiapina', 'olanzapina',
                'risperidona', 'pregabalina', 'gabapentina', 'carbamazepina', 'acido valproico', 'valcote',
                'lamotrigina', 'topiramato', 'sedante', 'ansiolitico', 'antidepresivo', 'anticonvulsivante',
                'valeriana', 'pasiflora', 'melatonina', 'somnifero', 'dormir', 'insomnio'
            ],
            'Dermatología y Cuidado de la Piel' => [
                'clotrimazol', 'ketoconazol', 'miconazol', 'terbinafina', 'isoconazol', 'fluconazol',
                'betametasona', 'hidrocortisona', 'mometasona', 'clobetasol', 'dexametasona', 'neomicina',
                'bacitracina', 'mupirocina', 'sulfadiazina de plata', 'cicatrizante', 'dermatitis', 'hongo',
                'antimicotico', 'crema', 'unguento', 'pomada', 'gel dermatologico', 'bloqueador', 'protector solar',
                'acne', 'peroxido de benzoilo', 'retinol', 'acido salicilico', 'urea', 'cerave', 'cetaphil',
                'adapaleno', 'zudenina', 'tretinoina', 'betarretin', 'benzoato de bencilo', 'nopucid', 'decametrina',
                'diklason', 'procicar', 'beducen', 'dexpantenol', 'bepanthen', 'caladryl', 'locion calamina'
            ],
            'Oftalmología y Salud Ocular' => [
                'gotas oftalmicas', 'colirio', 'lagrimas artificiales', 'lubricante ocular', 'hipromelosa',
                'carboximetilcelulosa', 'timolol', 'brimonidina', 'latanoprost', 'tobramicina', 'moxifloxacino oftalmico',
                'gentamicina oftalmica', 'nafazolina oftalmica', 'oftalmico', 'ojo', 'ojos', 'vision', 'quinoftal',
                'systane', 'refresh', 'splash', 'poen', 'oftal', 'gotas oft'
            ],
            'Salud Femenina y Masculina' => [
                'etinilestradiol', 'levonorgestrel', 'drospirenona', 'dienogest', 'anticonceptivo', 'pildora',
                'postday', 'emergencia', 'ovulo', 'ginecologico', 'tampon', 'toalla sanitaria', 'protector diario',
                'sildenafil', 'tadalafil', 'tamsulosina', 'finasteride', 'dutasteride', 'prostata', 'menopausia',
                'isoflavonas', 'progesterona', 'testosterona', 'ducha vaginal', 'sax', 'acido borico', 'vaginal',
                'clotrimazol vaginal', 'metronidazol vaginal', 'canesten ovulo'
            ],
            'Multivitamínicos y Minerales' => [
                'vitamina c', 'vitamina d', 'vitamina e', 'vitamina a', 'complejo b', 'acido folico',
                'hierro', 'sulfato ferroso', 'zinc', 'magnesio', 'citrato de magnesio', 'calcio', 'caltrate',
                'multivitaminico', 'pharmaton', 'centrum', 'supradyn', 'dayamineral', 'pediasure', 'ensure',
                'plexamin', 'suplemento nutricional', 'nutricional', 'biotina', 'folic', 'calcibon'
            ],
            'Salud Inmune y Defensas' => [
                'equinacea', 'propoleo', 'inmunologico', 'inmune', 'defensas', 'antioxidante', 'colageno',
                'omega 3', 'aceite de pescado', 'spirulina', 'moringa'
            ],
            'Nutrición Deportiva y Energía' => [
                'proteina', 'creatina', 'aminoacidos', 'bcaa', 'glutamina', 'energizante', 'shaker',
                'ganador de peso', 'whey protein', 'pre entreno', 'quemador de grasa'
            ],
            'Botiquín y Primeros Auxilios' => [
                'alcohol', 'gasas', 'gasa', 'algodon', 'agua oxigenada', 'isodine', 'yodopovidona', 'lodo povidona',
                'betadine', 'alcohol yodado', 'alumbre', 'azufre', 'venda', 'curita', 'aposito', 'esparadrapo',
                'micropore', 'jeringa', 'termometro', 'tensiometro', 'guantes', 'mascarilla', 'bisturi', 'aguja',
                'botiquin', 'cateter', 'sonda', 'sonda foley', 'mono de cirujano', 'bata quirurgica', 'gorro quirurgico',
                'bajalengua', 'torniquete', 'mariposa', 'infusor', 'venoclisis', 'scalp'
            ],
            'Higiene y Cuidado Diario' => [
                'jabon', 'shampoo', 'champu', 'acondicionador', 'desodorante', 'pasta dental', 'crema dental',
                'cepillo dental', 'hilo dental', 'enjuague bucal', 'afeitadora', 'crema de afeitar', 'locion',
                'talco', 'gel antibacterial', 'toallitas desinfectantes', 'papel higienico'
            ],
            'Mamá y Bebé' => [
                'pañal', 'pañales', 'toallitas humedas', 'biberon', 'tetero', 'chupete', 'crema antipañalitis',
                'desitin', 'hipp', 'nan', 's26', 'enfamil', 'formula infantil', 'leche maternizada',
                'extractor de leche', 'shampoo de bebe', 'jabon de bebe', 'aceite de bebe'
            ],
            'Salud Sexual' => [
                'preservativo', 'condon', 'lubricante intimo', 'gel intimo', 'prueba de embarazo',
                'test de embarazo', 'duo', 'prudence', 'durex', 'trojan'
            ],
        ];

        $products = \App\Models\Product::withoutGlobalScope('not_deleted')->get();
        $this->info("Iniciando clasificación de {$products->count()} productos...");

        $assignedCount = 0;

        foreach ($products as $product) {
            $textToMatch = mb_strtolower(
                ($product->name ?? '') . ' ' .
                ($product->active_ingredient ?? '') . ' ' .
                ($product->description ?? '')
            );

            $matchedCategoryId = null;

            foreach ($rules as $categoryName => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($textToMatch, mb_strtolower($keyword))) {
                        $matchedCategoryId = $categories[$categoryName]->id ?? null;
                        break 2;
                    }
                }
            }

            // Si aún no hace match con reglas específicas, aplicar discriminador por forma farmacéutica
            if (!$matchedCategoryId) {
                if (str_contains($textToMatch, 'jbe') || str_contains($textToMatch, 'jarabe') || str_contains($textToMatch, 'susp') || str_contains($textToMatch, 'antitus')) {
                    $matchedCategoryId = $categories['Respiratorio y Gripe']->id ?? 4;
                } elseif (str_contains($textToMatch, 'iny') || str_contains($textToMatch, 'amp') || str_contains($textToMatch, 'comp') || str_contains($textToMatch, 'tab') || str_contains($textToMatch, 'cap')) {
                    $matchedCategoryId = $categories['Dolor, Inflamación y Fiebre']->id ?? 3;
                } elseif (str_contains($textToMatch, 'crem') || str_contains($textToMatch, 'gel') || str_contains($textToMatch, 'ung') || str_contains($textToMatch, 'loc')) {
                    $matchedCategoryId = $categories['Dermatología y Cuidado de la Piel']->id ?? 7;
                } elseif (str_contains($textToMatch, 'jabon') || str_contains($textToMatch, 'desod') || str_contains($textToMatch, 'shamp')) {
                    $matchedCategoryId = $categories['Higiene y Cuidado Diario']->id ?? 15;
                } else {
                    $matchedCategoryId = $categories['Botiquín y Primeros Auxilios']->id ?? 14;
                }
            }

            $product->category_id = $matchedCategoryId;
            $product->save();
            $assignedCount++;
        }

        $this->info("¡Proceso completado con éxito!");
        $this->info("Total productos clasificados y asignados: {$assignedCount}");

        // Mostrar resumen por categoría
        $this->table(
            ['ID', 'Categoría', 'Total Productos'],
            \App\Models\Category::withCount('products')->get()->map(fn($c) => [
                $c->id,
                $c->name,
                $c->products_count
            ])
        );

        return 0;
    }
}
