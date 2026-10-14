<?php
/**
 * Cadastros em lista da área do cliente (contatos, créditos e operações).
 * Cada tipo é descrito uma vez aqui; as telas do cliente e do administrador usam a mesma descrição.
 */
declare(strict_types=1);

/**
 * Campo: [rótulo, tipo, tamanho máximo, obrigatório, opções].
 * Tipos: texto, email, dinheiro (centavos no banco), data (AAAA-MM-DD), longo, opcao.
 * A primeira situação é a inicial; enquanto o registro estiver nela o cliente pode alterar ou excluir.
 */
function RegistroDefs(): array
{
    return [
        'contatos' => [
            'titulo' => 'Pessoas de contato',
            'novo' => 'Nova pessoa de contato',
            'intro' => 'Pessoas da sua empresa ou fundo com quem a Pignoro pode falar.',
            'campos' => [
                'nome' => ['Nome', 'texto', 100, true],
                'cargo' => ['Cargo', 'texto', 80, false],
                'email' => ['E-mail', 'email', 150, false],
                'telefone' => ['Telefone', 'texto', 30, false],
            ],
            'situacoes' => [],
        ],
        'creditos' => [
            'titulo' => 'Créditos para cobrança',
            'novo' => 'Novo crédito',
            'intro' => 'Créditos em aberto que a Pignoro deve cobrar.',
            'campos' => [
                'devedor' => ['Devedor', 'texto', 120, true],
                'documento' => ['CPF ou CNPJ do devedor', 'texto', 20, false],
                'valor' => ['Valor (R$)', 'dinheiro', 20, true],
                'vencimento' => ['Vencimento', 'data', 10, false],
                'garantia' => ['Garantia', 'texto', 200, false],
                'observacoes' => ['Observações', 'longo', 2000, false],
            ],
            'situacoes' => ['novo' => 'Recebido', 'em_cobranca' => 'Em cobrança', 'acordo' => 'Em acordo',
                'recuperado' => 'Recuperado', 'encerrado' => 'Encerrado'],
        ],
        'operacoes' => [
            'titulo' => 'Operações',
            'novo' => 'Nova operação',
            'intro' => 'Operações casadas, de câmbio ou de hedge que você quer estruturar ou acompanhar.',
            'campos' => [
                'tipo' => ['Tipo', 'opcao', 20, true, ['casada' => 'Operação casada', 'cambio' => 'Câmbio', 'hedge' => 'Hedge']],
                'descricao' => ['Descrição', 'texto', 200, true],
                'moeda' => ['Moeda', 'texto', 10, false],
                'valor' => ['Valor', 'dinheiro', 20, false],
                'data_prevista' => ['Data prevista', 'data', 10, false],
                'observacoes' => ['Observações', 'longo', 2000, false],
            ],
            'situacoes' => ['nova' => 'Recebida', 'em_analise' => 'Em análise', 'em_andamento' => 'Em andamento',
                'concluida' => 'Concluída', 'cancelada' => 'Cancelada'],
        ],
    ];
}

/** Descrição do tipo pedido na URL, com o nome da tabela em 'tabela'. */
function RegistroDef(string $v_Tipo): array
{
    $Defs = RegistroDefs();
    if (!isset($Defs[$v_Tipo]))
        Abort(404, 'Página não encontrada.');
    return $Defs[$v_Tipo] + ['tabela' => $v_Tipo];
}

/** O cliente só altera o registro enquanto ele está na situação inicial. */
function RegistroEditavel(array $v_Def, array $v_Row): bool
{
    return !$v_Def['situacoes'] || $v_Row['situacao'] === array_key_first($v_Def['situacoes']);
}

/**
 * Valida os campos enviados. Devolve [valores, erro]; erro vazio quando está tudo certo.
 * Os nomes de coluna vêm da descrição acima, nunca do que o navegador envia.
 */
function RegistroLer(array $v_Def, array $v_Post): array
{
    $Values = [];
    foreach ($v_Def['campos'] as $Name => $Field) {
        [$Label, $Type, $Max, $Required] = $Field;
        $Raw = is_string($v_Post[$Name] ?? null) ? $v_Post[$Name] : '';
        $Value = $Type === 'longo' ? CleanText($Raw, $Max) : CleanLine($Raw, $Max);

        if ($Required && $Value === '')
            return [[], 'Preencha o campo "' . $Label . '".'];
        if ($Type === 'email' && $Value !== '' && !filter_var($Value, FILTER_VALIDATE_EMAIL))
            return [[], 'Informe um e-mail válido.'];
        if ($Type === 'data' && $Value !== '' && FormatDate($Value) === '')
            return [[], 'Informe uma data válida em "' . $Label . '".'];
        if ($Type === 'opcao' && !isset($Field[4][$Value]))
            return [[], 'Escolha uma opção em "' . $Label . '".'];
        if ($Type === 'dinheiro') {
            $Value = ParseMoney($Value);
            if ($Value === null)
                return [[], 'Informe um valor válido em "' . $Label . '", como 1.234,56.'];
        }
        $Values[$Name] = $Value;
    }
    return [$Values, ''];
}

function RegistroSalvar(array $v_Def, int $v_UserId, array $v_Values, int $v_Id): void
{
    $Cols = array_keys($v_Values);
    if ($v_Id > 0) {
        $Set = implode(', ', array_map(fn($c) => "$c = ?", $Cols));
        Query("UPDATE {$v_Def['tabela']} SET $Set WHERE id = ? AND usuario_id = ?",
            [...array_values($v_Values), $v_Id, $v_UserId]);
    } else {
        $Marks = implode(', ', array_fill(0, count($Cols) + 2, '?'));
        Query("INSERT INTO {$v_Def['tabela']} (" . implode(', ', $Cols) . ", usuario_id, criado_em) VALUES ($Marks)",
            [...array_values($v_Values), $v_UserId, time()]);
    }
}

function RegistroCelula(array $v_Field, $v_Value): string
{
    switch ($v_Field[1]) {
        case 'dinheiro':
            return (int) $v_Value === 0 ? '' : FormatMoney((int) $v_Value);
        case 'data':
            return FormatDate((string) $v_Value);
        case 'opcao':
            return E($v_Field[4][$v_Value] ?? '');
        case 'longo':
            return nl2br(E((string) $v_Value));
        default:
            return E((string) $v_Value);
    }
}

/**
 * Tabela com os registros. $v_Acoes recebe a linha e devolve o HTML da última coluna
 * (botões do cliente ou seletor de situação do administrador).
 */
function RegistroTabela(array $v_Def, array $v_Rows, callable $v_Acoes): void
{
    if (!$v_Rows) {
        echo '<p class="empty">Nenhum registro até agora.</p>';
        return;
    }
    echo '<div class="table-wrap"><table class="table"><thead><tr>';
    foreach ($v_Def['campos'] as $Field)
        echo '<th>' . E($Field[0]) . '</th>';
    if ($v_Def['situacoes'])
        echo '<th>Situação</th>';
    echo '<th><span class="sr-only">Ações</span></th></tr></thead><tbody>';
    foreach ($v_Rows as $Row) {
        echo '<tr>';
        foreach ($v_Def['campos'] as $Name => $Field)
            echo '<td data-label="' . E($Field[0]) . '">' . RegistroCelula($Field, $Row[$Name]) . '</td>';
        if ($v_Def['situacoes'])
            echo '<td data-label="Situação"><span class="tag">' . E($v_Def['situacoes'][$Row['situacao']] ?? $Row['situacao']) . '</span></td>';
        echo '<td class="table-actions">' . $v_Acoes($Row) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/** Campos do formulário, preenchidos com $v_Values (registro em edição ou envio com erro). */
function RegistroCampos(array $v_Def, array $v_Values): void
{
    foreach ($v_Def['campos'] as $Name => $Field) {
        [$Label, $Type, $Max, $Required] = $Field;
        $Value = $v_Values[$Name] ?? '';
        if ($Type === 'dinheiro' && is_int($Value))
            $Value = $Value === 0 ? '' : FormatMoney($Value);
        $Attrs = 'name="' . $Name . '" maxlength="' . $Max . '"' . ($Required ? ' required' : '');

        echo '<label class="pfield' . ($Type === 'longo' ? ' pfield-wide' : '') . '"><span>' . E($Label) . '</span>';
        if ($Type === 'longo') {
            echo '<textarea ' . $Attrs . ' rows="3">' . E((string) $Value) . '</textarea>';
        } elseif ($Type === 'opcao') {
            echo '<select name="' . $Name . '">';
            foreach ($Field[4] as $Key => $Text)
                echo '<option value="' . E($Key) . '"' . ($Key === $Value ? ' selected' : '') . '>' . E($Text) . '</option>';
            echo '</select>';
        } else {
            $Html = ['email' => 'email', 'data' => 'date'][$Type] ?? 'text';
            echo '<input type="' . $Html . '" ' . $Attrs . ' value="' . E((string) $Value) . '"'
                . ($Type === 'dinheiro' ? ' inputmode="decimal" placeholder="0,00"' : '') . '>';
        }
        echo '</label>';
    }
}
