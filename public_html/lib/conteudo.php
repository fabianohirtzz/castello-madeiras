<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Leitura do conteudo por secao. Toda funcao de listagem devolve linhas
 * associativas ja filtradas por ativo = 1 e ordenadas por ordem, id.
 */

/**
 * Origem publica do site, sem barra no fim. Usada em canonical, og:url e
 * og:image, que precisam ser absolutos.
 *
 * TROCAR NO DEPLOY FINAL: enquanto o dominio definitivo (castellomadeiras.com.br)
 * nao aponta para esta hospedagem, o valor e o subdominio de teste.
 */
const CASTELLO_URL = 'https://castello.tohospedando.com.br';

/**
 * Endereco da Castello, num lugar so: rodape, pagina de contato e /link leem
 * daqui. Mudou em outubro de 2026 (antes: Av. Patricio Lima, 843).
 */
const CASTELLO_ENDERECO_RUA    = 'Av. Severiano Albino Corrêa, 729';
const CASTELLO_ENDERECO_BAIRRO = 'São João Margem Direita';
const CASTELLO_ENDERECO_CIDADE = 'Tubarão, Santa Catarina';
/* A ficha do Google Meu Negocio (cid 12879021834196049302), lida em 2026-10-14. */
const CASTELLO_MAPA_BUSCA      = 'Castello Casas de Madeira, Av. Severiano Albino Corrêa, 729, Tubarão - SC';
const CASTELLO_MAPA_LINK       = 'https://maps.google.com/?cid=12879021834196049302';
const CASTELLO_MAPA_ROTA       = 'https://www.google.com/maps/dir/?api=1&destination=-28.4831792,-49.0363265';

/** URL do mapa embutido, sem chave de API. */
function mapa_embed(): string
{
    return 'https://www.google.com/maps?q=' . rawurlencode(CASTELLO_MAPA_BUSCA) . '&output=embed';
}

/** Conjunto fechado de icones do FAQ. Chaves fixadas pelo contrato. */
const CASTELLO_ICONES_FAQ = [
    'relogio'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
    'chave'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="15" cy="9" r="4"/><path d="m12 12-8 8M8 16l2 2M6 18l1.6 1.6"/></svg>',
    'planta'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h7M15 6h5M4 12h2M10 12h10M4 18h9M17 18h3"/><circle cx="13" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="8" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="2" fill="currentColor" stroke="none"/></svg>',
    'clima'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2M12 19v2M5 12H3M21 12h-2M6.3 6.3 4.9 4.9M19.1 19.1l-1.4-1.4M17.7 6.3l1.4-1.4M4.9 19.1l1.4-1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>',
    'escudo'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg>',
    'parede'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3z"/><path d="M3 9.7h9M3 14.3h9M16.4 5v14"/></svg>',
    'fundacao' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/></svg>',
    'garantia' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l2.1 1.5 2.6-.2.9 2.4 2.3 1.3-.6 2.5.6 2.5-2.3 1.3-.9 2.4-2.6-.2L12 21l-2.1-1.5-2.6.2-.9-2.4-2.3-1.3.6-2.5-.6-2.5 2.3-1.3.9-2.4 2.6.2L12 3Z"/><path d="m9 12 2 2 4-4"/></svg>',
];

/** @param string $modalidade 'pronta' ou 'flex' */
function modelos(string $modalidade): array
{
    $st = db()->prepare(
        'SELECT * FROM modelos WHERE modalidade = ? AND ativo = 1 ORDER BY ordem ASC, id ASC'
    );
    $st->execute([$modalidade]);

    return $st->fetchAll();
}

function portfolio(): array
{
    return db()->query('SELECT * FROM portfolio WHERE ativo = 1 ORDER BY ordem ASC, id ASC')->fetchAll();
}

function avaliacoes(): array
{
    return db()->query('SELECT * FROM avaliacoes WHERE ativo = 1 ORDER BY ordem ASC, id ASC')->fetchAll();
}

/**
 * @param int|null $limite null usa config.videos_na_home.
 *                         Zero ou negativo devolve todos os ativos.
 * @param string $contexto 'instagram' (reels da home) ou 'flex' (o que e a
 *                         Castello Flex, na pagina Flex).
 */
function videos(?int $limite = null, string $contexto = 'instagram'): array
{
    if ($limite === null) {
        $limite = (int) config_ler('videos_na_home', '8');
    }

    $st = db()->prepare('SELECT * FROM videos WHERE ativo = 1 AND contexto = ? ORDER BY ordem ASC, id ASC LIMIT ?');
    $st->bindValue(1, $contexto);
    $st->bindValue(2, $limite > 0 ? $limite : -1, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll();
}

/** @param string $contexto 'geral' ou 'flex' */
function faq(string $contexto = 'geral'): array
{
    $st = db()->prepare(
        'SELECT * FROM faq WHERE contexto = ? AND ativo = 1 ORDER BY ordem ASC, id ASC'
    );
    $st->execute([$contexto]);

    return $st->fetchAll();
}

/** @param string $contexto 'pronta' ou 'flex' */
function passos(string $contexto): array
{
    $st = db()->prepare(
        'SELECT * FROM passos WHERE contexto = ? AND ativo = 1 ORDER BY ordem ASC, id ASC'
    );
    $st->execute([$contexto]);

    return $st->fetchAll();
}

/** Texto avulso editavel. Valor vazio ou ausente cai no padrao. */
function bloco(string $chave, string $padrao = ''): string
{
    $st = db()->prepare('SELECT valor FROM blocos WHERE chave = ?');
    $st->execute([$chave]);
    $valor = $st->fetchColumn();

    if ($valor === false || $valor === null || $valor === '') {
        return $padrao;
    }

    return (string) $valor;
}

/** SVG do icone do FAQ. String vazia quando a chave nao existe. */
function icone_faq(string $chave): string
{
    return CASTELLO_ICONES_FAQ[$chave] ?? '';
}

/**
 * Texto de bloco com destaque: o trecho entre asteriscos vira
 * <span class="hl">. Tudo passa por e() antes, entao a marcacao que sai e so
 * essa. Um asterisco solto fica como esta.
 */
function realce(string $texto): string
{
    return (string) preg_replace('/\*([^*]+)\*/u', '<span class="hl">$1</span>', e($texto));
}

/**
 * Icones dos ambientes do card de modelo, no traco dos icones do catalogo da
 * Castello. A ordem importa: a primeira palavra que bater escolhe o desenho,
 * entao "Estudio aberto com cozinha" vira cama, nao sofa.
 */
const CASTELLO_ICONES_AMBIENTE = [
    ['hidromassagem', '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h18v3a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-3Z"/><path d="M6 12V6a2 2 0 0 1 4 0M7 20l-1 2M17 20l1 2M14 5v.01M17 3.5v.01M17 7v.01"/></svg>'],
    ['mezanino',      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 20h4v-4h4v-4h4V8h4V4M3 20h18"/></svg>'],
    ['sacada',        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v8H7zM3 11h18M4 11v9M20 11v9M3 20h18M8 11v9M12 11v9M16 11v9"/></svg>'],
    ['garagem',       '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 16v-3l2.2-5a2 2 0 0 1 1.8-1.2h10a2 2 0 0 1 1.8 1.2L21 13v3a1 1 0 0 1-1 1h-1M5 17H4a1 1 0 0 1-1-1M9 17h6M4 13h16"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>'],
    ['servi',         '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6.5h.01M11 6.5h4"/></svg>'],
    ['varanda',       '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-6 9 6M5 9v11M19 9v11M3 20h18M5 15h14M9 15v5M12 15v5M15 15v5"/></svg>'],
    ['estúdio',       '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 19v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6M3 16h18M6 11V8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3M13 11V8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3"/></svg>'],
    ['banh',          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V6a3 3 0 0 1 3-3h1"/><path d="M9 8h9a4.5 4.5 0 0 0-9 0Z"/><path d="M11 12v1.5M14 12v1.5M17 12v1.5M12.5 16v1.5M15.5 16v1.5"/></svg>'],
    ['sala',          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11V8a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v3"/><path d="M3 13a2 2 0 0 1 4 0v2h10v-2a2 2 0 0 1 4 0v5H3v-5ZM5 18v2M19 18v2"/></svg>'],
    ['cozinha',       '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11V8a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v3"/><path d="M3 13a2 2 0 0 1 4 0v2h10v-2a2 2 0 0 1 4 0v5H3v-5ZM5 18v2M19 18v2"/></svg>'],
    ['',              '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 19v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6M3 16h18M6 11V8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3M13 11V8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3"/></svg>'],
];

/**
 * Ambientes de um modelo, um por linha no painel, cada um com o seu icone.
 * Sem palavra conhecida o icone e a cama, que cobre quarto, suite e
 * dormitorio, os mais comuns.
 *
 * @return array<int,array{0:string,1:string}> lista de [svg, texto]
 */
function ambientes_modelo(?string $itens): array
{
    $saida = [];
    foreach (preg_split('/\R/u', (string) $itens) ?: [] as $linha) {
        $texto = trim($linha);
        if ($texto === '') {
            continue;
        }
        $chave = mb_strtolower($texto);
        foreach (CASTELLO_ICONES_AMBIENTE as [$palavra, $svg]) {
            if ($palavra === '' || mb_strpos($chave, $palavra) !== false) {
                $saida[] = [$svg, $texto];
                break;
            }
        }
    }

    return $saida;
}

/** Fotos da galeria do Ver mais: a foto do card, a planta e a foto extra. */
function galeria_modelo(array $m): array
{
    $fotos = [];
    $nome  = (string) $m['nome'];
    foreach ([
        ['foto', (string) ($m['foto_alt'] ?? '') !== '' ? (string) $m['foto_alt'] : 'Fachada do modelo ' . $nome],
        ['planta', 'Planta baixa do modelo ' . $nome],
        ['foto_extra', 'Outra vista do modelo ' . $nome],
    ] as [$coluna, $alt]) {
        $arquivo = trim((string) ($m[$coluna] ?? ''));
        if ($arquivo !== '') {
            $fotos[] = ['src' => $arquivo, 'alt' => $alt, 'planta' => $coluna === 'planta'];
        }
    }

    return $fotos;
}
