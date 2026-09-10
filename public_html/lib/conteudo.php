<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Leitura do conteudo por secao. Toda funcao de listagem devolve linhas
 * associativas ja filtradas por ativo = 1 e ordenadas por ordem, id.
 */

/** Conjunto fechado de icones do FAQ. Chaves fixadas pelo contrato. */
const CASTELLO_ICONES_FAQ = [
    'relogio'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
    'chave'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="15" cy="9" r="4"/><path d="m12 12-8 8M8 16l2 2M6 18l1.6 1.6"/></svg>',
    'planta'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h7M15 6h5M4 12h2M10 12h10M4 18h9M17 18h3"/><circle cx="13" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="8" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="2" fill="currentColor" stroke="none"/></svg>',
    'clima'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2M12 19v2M5 12H3M21 12h-2M6.3 6.3 4.9 4.9M19.1 19.1l-1.4-1.4M17.7 6.3l1.4-1.4M4.9 19.1l1.4-1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>',
    'escudo'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4.4-3.4 7.4-8 8-4.6-.6-8-3.6-8-8V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg>',
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
 */
function videos(?int $limite = null): array
{
    if ($limite === null) {
        $limite = (int) config_ler('videos_na_home', '8');
    }

    $st = db()->prepare('SELECT * FROM videos WHERE ativo = 1 ORDER BY ordem ASC, id ASC LIMIT ?');
    $st->bindValue(1, $limite > 0 ? $limite : -1, PDO::PARAM_INT);
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
