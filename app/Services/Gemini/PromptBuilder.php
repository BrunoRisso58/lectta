<?php

namespace App\Services\Gemini;

// Prompts enviados ao Gemini (resumo + flashcards).
//
// POR QUE A RESPOSTA NÃO É JSON: o resumo é markdown, e markdown dentro de uma
// string JSON obriga o modelo a escapar cada quebra de linha (\n) e cada aspa
// (\"). É exatamente aí que ele erra -- e o erro é silencioso: com
// responseSchema, o decodificador restrito trata a aspa não escapada como o
// fim da string e emite o resto do objeto normalmente. O resultado é um JSON
// perfeitamente válido com o resumo cortado no meio da frase e sem nenhuma
// quebra de linha (todos os bullets viram um só). Sem schema, o mesmo erro
// virava "JSON inválido" e derrubava a aula.
//
// Em texto puro com marcadores não existe escape nenhum pra o modelo errar: o
// markdown sai literal, e uma resposta cortada pelo limite de tokens ainda
// vale (o que já veio continua parseável).
final class PromptBuilder
{
    public const RESP_SUMMARY_MARKER = '<<<RESUMO>>>';
    public const RESP_CARDS_MARKER = '<<<FLASHCARDS>>>';

    /**
     * Critério de o que MERECE virar flashcard -- igual nos dois prompts.
     *
     * {$max} é TETO, nunca meta. Antes o prompt pedia "aproximadamente N cards", e
     * um número pedido é um número entregue: quando a aula não tinha N pontos que
     * valessem revisão, o modelo completava a conta com o que sobrava -- data de
     * prova, anedota do professor, um nome citado de passagem. Um card ruim custa
     * caro no SM-2: ele volta pra revisão pra sempre, competindo com os bons.
     */
    public static function flashcardRules(int $max, string $subject): string
    {
        return <<<RULES
    Gere NO MÁXIMO {$max} cards -- e menos que isso sempre que a aula não render tanto.
    Três cards bons valem mais que dez, e é melhor não gerar nenhum de um trecho vazio do
    que forçar. Nunca crie um card só pra chegar num número.

    Antes de escrever cada card, aplique este teste -- se a resposta for "não", descarte:
    "se a pessoa errar essa pergunta daqui a um mês, ela ficou pior em {$subject}?"

    NÃO vire card (mesmo tendo sido dito na aula):
    - logística e avisos: data de prova, trabalho, o que vem na próxima aula, página do livro
    - digressão, anedota, piada, história pessoal, exemplo que o professor usou só pra ilustrar
    - o meta da aula em si: quem falou, o que o professor enfatizou, como a aula foi dividida,
      "o que o professor disse sobre X" (pergunte sobre X, não sobre o professor)
    - nome, número ou termo que apareceu de passagem e não chegou a ser explicado
    - pergunta genérica demais, que dá pra responder sem ter assistido a aula

    Cada card cobre UM fato ou conceito, e a pergunta tem que fazer sentido sozinha, sem
    depender de "segundo a aula" ou "no trecho acima".
    RULES;
    }

    /** Trecho do prompt que descreve o formato da resposta -- igual nos dois
     *  prompts (aula inteira e trecho), por isso fica num lugar só. */
    public static function responseFormat(int $targetFlashcards, string $summaryScope): string
    {
        return <<<FORMAT
    FORMATO DA RESPOSTA -- siga exatamente, sem nenhum texto fora dele:

    <<<RESUMO>>>
    (aqui o resumo {$summaryScope} em markdown de verdade, com quebras de linha
    reais. Use "## Título do tópico" para cada tópico, "- " para os bullets e
    quatro espaços antes do "- " para sub-bullets. Escreva markdown normal: aspas,
    acentos e símbolos podem ser usados à vontade, sem escapar nada.)

    <<<FLASHCARDS>>>
    P: primeira pergunta
    R: primeira resposta
    P: segunda pergunta
    R: segunda resposta
    (...um par P/R por card, cada um numa linha só, sem numeração e sem linha em
    branco entre eles. A quantidade é a que o conteúdo pedir, respeitando o teto
    de {$targetFlashcards}.)
    FORMAT;
    }

    public static function summary(string $transcript, string $classTitle, string $subject, int $targetFlashcards): string
    {
        $format = self::responseFormat($targetFlashcards, 'da aula');
        $cardRules = self::flashcardRules($targetFlashcards, $subject);
        return <<<PROMPT
    Você é um assistente de estudos que transforma a transcrição de uma aula em
    material de revisão ENXUTO. A matéria é "{$subject}" e o título/tema da aula é "{$classTitle}".

    A pessoa que vai estudar por esse material NÃO fez nenhuma anotação durante a aula, mas
    o objetivo aqui é um resumo rápido de revisão -- não uma segunda transcrição. Prioridade
    número um: SER CURTO. O resumo tem que ficar bem mais curto que a transcrição (uma fração
    pequena do tamanho original), cobrindo só os pontos que realmente importam pra revisar
    depois.

    Gere DUAS coisas a partir da transcrição abaixo:

    1. **Resumo estruturado** (markdown), em bullet points curtos organizados por
       tópico/seção na ordem em que apareceram na aula. Para cada tópico:
       - vá direto à ideia central, numa frase ou duas -- não reconte a explicação inteira do
         professor nem reproduza trechos longos da fala
       - inclua só os detalhes que têm valor de revisão de verdade: nomes, datas, termos
         técnicos com definição curta, referências citadas. Ignore digressões, repetições e
         comentários de contexto que não ajudam a estudar.
       - sinalize brevemente só as ênfases fortes do professor ("isso cai na prova" etc), sem
         virar uma seção própria
       Não invente conteúdo que não esteja na transcrição. Evite parágrafos longos -- prefira
       listas curtas.

    2. **Flashcards**: perguntas e respostas objetivas para revisão espaçada, com resposta
       curta e verificável (não pergunta de redação longa).

       {$cardRules}

    {$format}

    --- TRANSCRIÇÃO DA AULA ---
    {$transcript}
    --- FIM DA TRANSCRIÇÃO ---
    PROMPT;
    }

    // Usado só pra aulas longas, quando a transcrição inteira não coube numa única
    // chamada ao Gemini (ver split_transcript_into_parts em gemini_client.php) --
    // gera o resumo/flashcards de UM trecho por vez. As partes são concatenadas depois,
    // por isso o texto pede pra não escrever introdução/conclusão como se fosse a
    // aula inteira, já que aqui é só um pedaço dela.
    public static function partialSummary(
        string $transcriptPart,
        string $classTitle,
        string $subject,
        int $partNumber,
        int $totalParts,
        int $targetFlashcards
    ): string {
        $format = self::responseFormat($targetFlashcards, 'deste trecho');
        $cardRules = self::flashcardRules($targetFlashcards, $subject);
        return <<<PROMPT
    Você é um assistente de estudos que transforma a transcrição de uma aula em
    material de revisão ENXUTO. A matéria é "{$subject}" e o título/tema da aula é "{$classTitle}".

    Este é o trecho {$partNumber} de {$totalParts} da transcrição completa (ela foi dividida
    em partes só por causa do tamanho -- trate o conteúdo abaixo como uma continuação direta
    do que veio antes, sem escrever uma introdução ou conclusão como se este trecho fosse a
    aula inteira).

    O objetivo é um resumo rápido de revisão -- não uma segunda transcrição. Prioridade
    número um: SER CURTO. O resumo deste trecho tem que ficar bem mais curto que o trecho
    original, cobrindo só os pontos que realmente importam pra revisar depois.

    Gere DUAS coisas a partir do trecho de transcrição abaixo:

    1. **Resumo estruturado** (markdown) deste trecho, em bullet points curtos organizados
       por tópico, na ordem em que apareceram. Para cada tópico:
       - vá direto à ideia central, numa frase ou duas -- não reconte a explicação inteira
         nem reproduza trechos longos da fala
       - inclua só os detalhes com valor de revisão: nomes, datas, termos técnicos com
         definição curta, referências citadas. Ignore digressões e repetições.
       Não invente conteúdo que não esteja na transcrição. Evite parágrafos longos -- prefira
       listas curtas.

    2. **Flashcards**: perguntas e respostas objetivas para revisão espaçada, com resposta
       curta e verificável, cobrindo só os pontos deste trecho que valem revisão.

       {$cardRules}

    {$format}

    --- TRECHO {$partNumber}/{$totalParts} DA TRANSCRIÇÃO ---
    {$transcriptPart}
    --- FIM DO TRECHO ---
    PROMPT;
    }

    /** Formato de resposta só com flashcards (sem resumo) -- usado quando a pessoa
     *  já tem a aula pronta e pede mais cards na tela de edição. */
    public static function flashcardsOnlyFormat(int $max): string
    {
        return <<<FORMAT
    FORMATO DA RESPOSTA -- responda só com os flashcards, sem nenhum outro texto antes ou
    depois (sem introdução, sem markdown de resumo, sem comentário):

    P: primeira pergunta
    R: primeira resposta
    P: segunda pergunta
    R: segunda resposta
    (...um par P/R por card, cada um numa linha só, sem numeração e sem linha em
    branco entre eles. Gere no máximo {$max} cards -- ou nenhum, se não sobrar
    conteúdo novo que valha a pena.)
    FORMAT;
    }

    /** Prompt pra gerar MAIS flashcards de uma aula que já foi processada --
     *  reaproveita a transcrição já salva e evita repetir os cards que já existem. */
    public static function moreFlashcards(
        string $transcript,
        string $classTitle,
        string $subject,
        array $existingQuestions,
        int $count
    ): string {
        $cardRules = self::flashcardRules($count, $subject);
        $format = self::flashcardsOnlyFormat($count);
        $existingList = $existingQuestions
            ? "Esta aula JÁ TEM os flashcards abaixo -- não repita a mesma pergunta nem gere\n" .
              "outra pergunta muito parecida com elas:\n" .
              implode("\n", array_map(static fn(string $q): string => "- {$q}", $existingQuestions))
            : 'Esta aula ainda não tem nenhum flashcard.';

        return <<<PROMPT
    Você é um assistente de estudos. A matéria é "{$subject}" e o título/tema da aula é "{$classTitle}".

    A pessoa já estudou o resumo desta aula e quer MAIS flashcards de revisão espaçada, a
    partir da transcrição completa abaixo, cobrindo pontos que os cards existentes ainda não
    cobrem.

    {$existingList}

    {$cardRules}

    {$format}

    --- TRANSCRIÇÃO DA AULA ---
    {$transcript}
    --- FIM DA TRANSCRIÇÃO ---
    PROMPT;
    }
}
