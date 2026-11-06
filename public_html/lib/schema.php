<?php
/**
 * Dados estruturados (schema.org em JSON-LD) das paginas publicas. Tudo sai do
 * banco e das constantes de conteudo.php: mudou preco ou pergunta no painel,
 * muda aqui junto.
 *
 * Sem aggregateRating de proposito: as regras do Google nao aceitam nota de
 * empresa marcada no proprio site, nem avaliacoes copiadas de outra plataforma.
 */
declare(strict_types=1);

/** A empresa, igual em todas as paginas. */
function schema_empresa(): array
{
    return [
        '@type'         => 'GeneralContractor',
        '@id'           => CASTELLO_URL . '/#empresa',
        'name'          => 'Castello Casas de Madeira',
        'alternateName' => 'Castello Madeiras',
        'url'           => CASTELLO_URL . '/',
        'logo'          => CASTELLO_URL . '/images/logo-horizontal-colorido.png',
        'image'         => CASTELLO_URL . '/images/og-home.jpg',
        'description'   => 'Construtora de casas de madeira em Tubarão, Santa Catarina, desde 2014. Casa Pronta, completa em 90 a 120 dias, e Castello Flex, montada, coberta e fechada em 45 dias.',
        'telephone'     => '+55-48-99824-4494',
        'foundingDate'  => '2014',
        'address'       => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => CASTELLO_ENDERECO_RUA . ', ' . CASTELLO_ENDERECO_BAIRRO,
            'addressLocality' => 'Tubarão',
            'addressRegion'   => 'SC',
            'postalCode'      => CASTELLO_ENDERECO_CEP,
            'addressCountry'  => 'BR',
        ],
        'geo'        => ['@type' => 'GeoCoordinates', 'latitude' => -28.4831792, 'longitude' => -49.0363265],
        'hasMap'     => CASTELLO_MAPA_LINK,
        'areaServed' => ['@type' => 'State', 'name' => 'Santa Catarina'],
        'openingHoursSpecification' => [
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '08:00', 'closes' => '12:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '13:30', 'closes' => '18:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Saturday', 'opens' => '08:30', 'closes' => '12:00'],
        ],
        'sameAs' => [
            'https://www.instagram.com/castellocasasdemadeira/',
            'https://www.facebook.com/castellomadeiras',
            CASTELLO_MAPA_LINK,
        ],
    ];
}

/** Texto corrido: sem o negrito do painel e sem linha em branco. */
function schema_texto(string $texto): string
{
    return trim((string) preg_replace('/\s+/u', ' ', str_replace('**', '', $texto)));
}

function schema_faq(string $contexto): array
{
    $perguntas = [];
    foreach (faq($contexto) as $f) {
        $perguntas[] = [
            '@type'          => 'Question',
            'name'           => schema_texto((string) $f['pergunta']),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => schema_texto((string) $f['resposta'])],
        ];
    }

    return ['@type' => 'FAQPage', 'mainEntity' => $perguntas];
}

function schema_trilha(string $nome, string $arquivo): array
{
    return [
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => CASTELLO_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $nome, 'item' => CASTELLO_URL . '/' . $arquivo],
        ],
    ];
}

function schema_servico(string $nome, string $tipo, string $descricao, string $arquivo): array
{
    return [
        '@type'       => 'Service',
        'name'        => $nome,
        'serviceType' => $tipo,
        'description' => $descricao,
        'url'         => CASTELLO_URL . '/' . $arquivo,
        'provider'    => ['@id' => CASTELLO_URL . '/#empresa'],
        'areaServed'  => ['@type' => 'State', 'name' => 'Santa Catarina'],
    ];
}

/** Preco do banco ("108.000") em numero; 0 quando o modelo esta sob consulta. */
function schema_preco(?string $preco): int
{
    return (int) preg_replace('/\D/', '', (string) $preco);
}

/**
 * Os modelos com preco. Na Casa Pronta o valor e "a partir de", porque todo
 * projeto e personalizavel; na Flex o preco e fechado.
 */
function schema_modelos(string $modalidade, string $arquivo): array
{
    $url   = CASTELLO_URL . '/' . $arquivo;
    $itens = [];
    foreach (modelos($modalidade) as $m) {
        $preco = schema_preco($m['preco'] ?? '');
        if ($preco === 0) {
            continue;
        }
        $ambientes = array_filter(array_map('trim', preg_split('/\R/u', (string) ($m['itens'] ?? '')) ?: []));
        $produto = [
            '@type'       => 'Product',
            'name'        => $modalidade === 'pronta' ? 'Casa Pronta ' . $m['nome'] : $m['nome'],
            'description' => trim('Casa de madeira de ' . $m['area'] . '. ' . implode(', ', $ambientes)),
            'brand'       => ['@type' => 'Brand', 'name' => 'Castello Casas de Madeira'],
            'offers'      => $modalidade === 'pronta'
                ? ['@type' => 'AggregateOffer', 'lowPrice' => $preco, 'priceCurrency' => 'BRL', 'offerCount' => 1, 'url' => $url]
                : ['@type' => 'Offer', 'price' => $preco, 'priceCurrency' => 'BRL', 'url' => $url],
        ];
        if (!empty($m['foto'])) {
            $produto['image'] = CASTELLO_URL . '/' . ltrim((string) $m['foto'], '/');
        }
        $itens[] = ['@type' => 'ListItem', 'position' => count($itens) + 1, 'item' => $produto];
    }

    return ['@type' => 'ItemList', 'itemListElement' => $itens];
}

/**
 * O bloco <script> de uma pagina. O FAQ geral aparece na home e na Casa Pronta,
 * mas e marcado so na home: o Google pede uma marcacao por conteudo repetido.
 *
 * @param string $pagina home, pronta, flex, portfolio, depoimentos ou contato
 */
function schema_pagina(string $pagina): string
{
    $grafo = [schema_empresa()];

    switch ($pagina) {
        case 'home':
            $grafo[] = [
                '@type'      => 'WebSite',
                '@id'        => CASTELLO_URL . '/#site',
                'url'        => CASTELLO_URL . '/',
                'name'       => 'Castello Casas de Madeira',
                'inLanguage' => 'pt-BR',
                'publisher'  => ['@id' => CASTELLO_URL . '/#empresa'],
            ];
            $grafo[] = schema_faq('geral');
            break;
        case 'pronta':
            $grafo[] = schema_trilha('Casa Pronta', 'casa-pronta.php');
            $grafo[] = schema_servico('Casa Pronta', 'Construção de casa de madeira chave na mão',
                'Casa de madeira completa e personalizável, com entrega de 90 a 120 dias.', 'casa-pronta.php');
            $grafo[] = schema_modelos('pronta', 'casa-pronta.php');
            break;
        case 'flex':
            $grafo[] = schema_trilha('Castello Flex', 'flex.php');
            $grafo[] = schema_servico('Castello Flex', 'Construção de casa de madeira montada e fechada',
                'Casa de madeira em plantas padronizadas, montada, coberta e fechada no terreno do cliente em 45 dias. O acabamento fica por conta do cliente.', 'flex.php');
            $grafo[] = schema_modelos('flex', 'flex.php');
            $grafo[] = schema_faq('flex');
            break;
        case 'portfolio':
            $grafo[] = schema_trilha('Portfólio', 'portfolio.php');
            break;
        case 'depoimentos':
            $grafo[] = schema_trilha('Depoimentos', 'depoimentos.php');
            break;
        case 'contato':
            $grafo[] = schema_trilha('Contato', 'contato.php');
            break;
    }

    $json = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $grafo],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
    );

    return '<script type="application/ld+json">' . $json . '</script>';
}
