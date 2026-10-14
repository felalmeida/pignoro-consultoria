<?php
/**
 * Idiomas do site: português (base), inglês e espanhol.
 * Os textos são escritos em português no código e passam por T(); as traduções ficam em
 * app/idiomas/en.php e es.php, com o texto em português como chave. Texto sem tradução sai em português.
 */
declare(strict_types=1);

const IDIOMAS = ['pt' => 'Português', 'en' => 'English', 'es' => 'Español'];
const IDIOMA_HTML = ['pt' => 'pt-BR', 'en' => 'en', 'es' => 'es'];

/** Idioma em uso nesta requisição. */
function Lang(?string $v_Set = null): string
{
    static $Lang = 'pt';
    if ($v_Set !== null && isset(IDIOMAS[$v_Set]))
        $Lang = $v_Set;
    return $Lang;
}

/** Idioma válido ou null. */
function ValidLang($v_Value): ?string
{
    return is_string($v_Value) && isset(IDIOMAS[$v_Value]) ? $v_Value : null;
}

/** Idioma preferido pelo navegador (cabeçalho Accept-Language), entre os disponíveis. */
function BrowserLang(): string
{
    $Best = 'pt';
    $BestQ = -1.0;
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $Part) {
        if (!preg_match('/^\s*([a-z]{2})(?:-[A-Za-z0-9]+)?\s*(?:;\s*q=([0-9.]+))?/i', $Part, $M))
            continue;
        $Code = strtolower($M[1]);
        $Q = isset($M[2]) ? (float) $M[2] : 1.0;
        if (isset(IDIOMAS[$Code]) && $Q > $BestQ) {
            $Best = $Code;
            $BestQ = $Q;
        }
    }
    return $Best;
}

/** Dicionário do idioma: 'php' para os textos do servidor, 'js' para os do JavaScript. */
function Dictionary(string $v_Lang, string $v_Kind = 'php'): array
{
    static $Cache = [];
    if ($v_Lang === 'pt')
        return [];
    $Key = $v_Lang . '-' . $v_Kind;
    if (!isset($Cache[$Key])) {
        $File = __DIR__ . '/idiomas/' . $v_Lang . ($v_Kind === 'js' ? '-js' : '') . '.php';
        $Cache[$Key] = is_file($File) ? (array) require $File : [];
    }
    return $Cache[$Key];
}

/**
 * Tradução do texto em português. {nome} no texto é trocado pelo valor de $v_Vars['nome'].
 * $v_Lang força um idioma (por exemplo, o do cliente num e-mail); sem ele vale o da requisição.
 */
function T(string $v_Pt, array $v_Vars = [], ?string $v_Lang = null): string
{
    $Text = Dictionary($v_Lang ?? Lang())[$v_Pt] ?? $v_Pt;
    foreach ($v_Vars as $Name => $Value)
        $Text = str_replace('{' . $Name . '}', (string) $Value, $Text);
    return $Text;
}
