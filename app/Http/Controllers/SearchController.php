<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private $client;
    private $esHost;

    public function __construct()
    {
        $this->client = new Client();
        $this->esHost = 'localhost:9200'; // لوکال Elasticsearch
    }

    public function index()
    {
        return view('search.index');
    }

    public function search(Request $request): JsonResponse
    {
        $searchQuery = $request->input('search');

        $result = [
            'categories' => [],
            'products' => [],
            'suggestions' => [],
            'error' => null
        ];

        if (empty(trim($searchQuery))) {
            return response()->json($result);
        }

        try {
            $firstWord = $this->getFirstWord($searchQuery);

            // جستجوی دسته‌بندی‌ها
            $categoryParams = [
                'json' => [
                    'query' => [
                        'function_score' => [
                            'query' => [
                                'bool' => [
                                    'should' => [
// تطابق دقیق با نام دسته‌بندی
                                        [
                                            'match_phrase' => [
                                                'name' => [
                                                    'query' => $searchQuery,
                                                    'slop' => 0, // بدون انعطاف در ترتیب یا فاصله کلمات
                                                    'boost' => 1000 // بالاترین امتیاز برای تطابق دقیق
                                                ]
                                            ]
                                        ],
// تطابق‌های جزئی
                                        [
                                            'multi_match' => [
                                                'query' => $searchQuery,
                                                'fields' => ['name^5', 'keyword^3'],
                                                'type' => 'best_fields',
                                                'fuzziness' => 'AUTO',
                                                'prefix_length' => 0,
                                                'operator' => 'or',
                                                'boost' => 50
                                            ]
                                        ],
                                        [
                                            'match_phrase_prefix' => [
                                                'name' => [
                                                    'query' => $firstWord,
                                                    'slop' => 1,
                                                    'boost' => 20
                                                ]
                                            ]
                                        ]
                                    ],
                                    'minimum_should_match' => 1
                                ]
                            ],
                            'functions' => [
                                [
                                    'filter' => [
                                        'exists' => [
                                            'field' => 'brands'
                                        ]
                                    ],
                                    'weight' => 100
                                ],
                                [
                                    'script_score' => [
                                        'script' => [
                                            'source' => "doc['brands'].size() > 0 ? doc['brands'].size() * 10 : 0"
                                        ]
                                    ]
                                ]
                            ],
                            'score_mode' => 'sum',
                            'boost_mode' => 'sum'
                        ]
                    ],
                    'size' => 5,
                    'aggs' => [
                        'by_category' => [
                            'terms' => [
                                'field' => 'name.keyword',
                                'size' => 5,
                                'order' => ['_count' => 'desc']
                            ],
                            'aggs' => [
                                'category_details' => [
                                    'top_hits' => [
                                        'size' => 1,
                                        '_source' => ['name', 'slug', 'brands']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // جستجوی محصولات
            $productParams = [
                'json' => [
                    'query' => [
                        'function_score' => [
                            'query' => [
                                'bool' => [
                                    'should' => [
                                        [
                                            'prefix' => [
                                                'name' => [
                                                    'value' => $firstWord,
                                                    'boost' => 10
                                                ]
                                            ]
                                        ],
                                        [
                                            'multi_match' => [
                                                'query' => $searchQuery,
                                                'fields' => ['name^5', 'keyword^3', 'bodySeo^2', 'body^1.5', 'specifications^1'],
                                                'type' => 'best_fields',
                                                'fuzziness' => 'AUTO',
                                                'prefix_length' => 0,
                                                'operator' => 'or'
                                            ]
                                        ],
                                        [
                                            'match_phrase_prefix' => [
                                                'name' => [
                                                    'query' => $firstWord,
                                                    'slop' => 1,
                                                    'boost' => 20
                                                ]
                                            ]
                                        ]
                                    ],
                                    'minimum_should_match' => 1
                                ]
                            ],
                            'functions' => [
                                [
                                    'filter' => [
                                        'exists' => [
                                            'field' => 'brands'
                                        ]
                                    ],
                                    'weight' => 100
                                ],
                                [
                                    'script_score' => [
                                        'script' => [
                                            'source' => "doc['brands'].size() > 0 ? doc['brands'].size() * 10 : 0"
                                        ]
                                    ]
                                ]
                            ],
                            'score_mode' => 'sum',
                            'boost_mode' => 'sum'
                        ]
                    ],
                    'size' => 5,
                    '_source' => ['name', 'slug', 'brands']
                ]
            ];


            // اجرای کوئری‌ها
            $categoryResponse = $this->client->request('GET', "{$this->esHost}/categories/_search", $categoryParams);
            $categoryResults = json_decode($categoryResponse->getBody()->getContents(), true);

            $productResponse = $this->client->request('GET', "{$this->esHost}/products/_search", $productParams);
            $productResults = json_decode($productResponse->getBody()->getContents(), true);

            // پردازش نتایج دسته‌بندی‌ها
            if (!empty($categoryResults['hits']['hits'])) {
                foreach ($categoryResults['hits']['hits'] as $hit) {
                    if (isset($hit['_source']['name']) && !empty($hit['_source']['name'])) {
                        $result['categories'][] = [
                            'name' => $this->shortenTitle($hit['_source']['name']),
                            'slug' => $hit['_source']['slug'] ?? $this->slugify($hit['_source']['name'])
                        ];
                    }
                }
            }

            // پردازش نتایج محصولات
            if (!empty($productResults['hits']['hits'])) {
                foreach ($productResults['hits']['hits'] as $hit) {
                    if (isset($hit['_source']['name']) && !empty($hit['_source']['name'])) {
                        $result['products'][] = [
                            'name' => $this->shortenTitle($hit['_source']['name']),
                            'slug' => $hit['_source']['slug'] ?? $this->slugify($hit['_source']['name']),
                            'price' => $hit['_source']['price'] ?? null
                        ];
                    }
                }
            }

            // پیشنهادات (مشابه دسته‌بندی‌ها ولی متفاوت)
            $result['suggestions'] = $this->removeDuplicates($result['categories'], $result['categories']);

        } catch (\Exception $e) {
            $result['error'] = 'خطا در اتصال به Elasticsearch: ' . $e->getMessage();
        }

        return response()->json($result);
    }

    private function shortenTitle($title, $maxLength = 40)
    {
        $stopWords = ['با', 'دارای', 'قابل', 'تنظیم', 'و', 'از', 'در', 'برای', 'به', 'همراه', 'مجهز', 'مناسب', 'ویژه', 'اصل', 'جدید'];
        $words = explode(' ', $title);
        $importantWords = [];

        foreach ($words as $word) {
            if (!in_array($word, $stopWords)) {
                $importantWords[] = $word;
            }
        }

        $shortened = implode(' ', $importantWords);
        if (mb_strlen($shortened) > $maxLength) {
            $shortened = mb_substr($shortened, 0, $maxLength) . '...';
        }

        return $shortened ?: $title;
    }

    private function getFirstWord($query)
    {
        $words = explode(' ', trim($query));
        return $words[0] ?? '';
    }

    private function slugify($text)
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(' ', '-', $text);
        $text = preg_replace('/[^\p{L}\p{N}\-]/u', '', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'item';
    }

    private function removeDuplicates($categories, $suggestions)
    {
        $categoryNames = array_column($categories, 'name');
        return array_filter($suggestions, function ($suggestion) use ($categoryNames) {
            return !in_array($suggestion['name'], $categoryNames);
        });
    }
}
