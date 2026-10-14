=== ML CSS Pro ===
Contributors: mlopesdesign
Tags: css, nicepage, admin, license, custom-css, scanner
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.5.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editor visual de CSS para WordPress e Nicepage com scanner de CSS do frontend, deteccao real de seletores, override visual, editor de codigo, validacao, snippets, historico e cache compilado.

== Description ==
ML CSS Pro detecta os estilos reais do seu site (cores, fontes, botoes, header, rodape, formularios, layout) e permite editar os valores detectados diretamente no painel. Overrides sao compilados em wp-content/uploads/ml-css-pro/compiled.css com cache-busting automatico.

== Changelog ==

= 1.5.5 =
* Atualizacoes passam a vir do repositorio publico mlopesdesign/ml-css-pro-releases (somente ZIPs).
* Atualizador: so o ZIP exato ml-css-pro-vX.Y.Z.zip (sem "qualquer ZIP" e sem zipball); "sem atualizacao" correto; icones; falha da API em cache por 15 min (antes 6 h); sem rotina pos-instalacao nem ativacao forcada.
* Desinstalar preserva o CSS do usuario (configuracoes, presets, historico e overrides) e a licenca; remove so caches e dados regeneraveis.

= 1.5.4 =
* Emergency correction for visual editor click selection regression after responsive mode.
* Prevent responsive device mode from resizing the root HTML and pushing the page behind the fixed sidebar.
* Restore stable v1.4.9/v1.5.0 click-to-select behavior while preserving device-specific save context.
= 1.5.1 =
* Edicao por pagina/URL: escolha qualquer pagina, post ou produto publico e abra o editor visual diretamente nela.
* Seletor de paginas com busca paginada por titulo (AJAX) e opcao de mostrar rascunhos.
* Campo Editar URL especifica com validacao: mesmo dominio, rejeita wp-admin/login/ajax/rest e dominios externos.
* Editor visual abre na pagina escolhida via ?mlcssp_visual=1 (nao mais apenas home).
* Deteccao de contexto: URL, titulo, post ID, post type, classe do body (page-id-N / postid-N).
* Escopo de salvamento: Site inteiro ou Apenas esta pagina.
* Overrides por pagina compilados com prefixo body.page-id-N e media queries responsivas por pagina.
* Armazenamento separado mlcsspro_page_overrides — nunca afeta overrides globais.
* Reset por pagina (elemento/pagina inteira) sem afetar outras paginas ou o global.
* Diagnostico de Aplicacao com contagem de overrides por pagina.
* Pipelines v1.4.9 (global) e v1.5.0 (responsivo) congelados e preservados.
* Updater GitHub preservado.
= 1.5.0 =
* Edicao responsiva: modos Desktop (1200px), Tablet (768px) e Mobile (375px) com switcher no editor visual.
* Armazenamento separado por dispositivo: desktop em mlcsspro_overrides, tablet/mobile em mlcsspro_overrides_responsive.
* Media queries geradas: Tablet @media (max-width:1024px), Mobile @media (max-width:767px), anexadas ao compiled.css e ao fallback inline.
* Salvamento por dispositivo: salva apenas o override do dispositivo ativo.
* Indicador Editando: Desktop/Tablet/Mobile e simulacao de viewport no preview.
* Diagnostico responsivo: contagem de overrides por dispositivo.
* Reset responsivo por dispositivo sem afetar os outros.
* Historico responsivo: seletor, propriedade, valor, dispositivo, timestamp.
* Export/import de todos os dispositivos.
* Pipeline estavel da v1.4.9 congelado e preservado integralmente.
* Updater GitHub preservado.
= 1.4.9 =
* Editor visual expandido: 12 secoes colapsaveis (Texto, Fundo, Espacamento, Bordas, Arredondamento, Tamanho, Posicao, Sombra, Transformar, Filtros, Visibilidade, Avancado).
* +60 propriedades CSS controlaveis: text-shadow, background-size/position/repeat/attachment, bordas individuais, radius por canto, width/height/min/max, position/top/right/bottom/left/z-index, box-shadow, transform, filter, display/visibility/overflow/cursor/pointer-events.
* Salvamento multi-propriedade: salva todas as alteracoes pendentes de uma vez, valida cada uma, recompila uma vez.
* Desfazer preview, resetar propriedade e resetar seletor (sem afetar outros seletores).
* Controles visuais: color picker, slider+numero, botoes segmentados, dropdowns.
* Pipeline de aplicacao da v1.4.8 preservado integralmente (storage, compiled.css, inline fallback, enqueue, cache-busting, selector publico).
* Updater GitHub preservado.
= 1.4.8 =
* CORRIGIDO: color picker nao sincronizava com campo de texto — cor escolhida nunca era enviada ao servidor.
* CORRIGIDO: elemento selecionado agora recebe classe persistente mlcssp-ve-selected com outline visivel.
* CORRIGIDO: painel agora le overrides salvos primeiro (via C.overrides do PHP) antes de usar computed style.
* CORRIGIDO: apos salvar, valores ficam no painel em vez de reverter ao computed original.
* ADICIONADO: overrides salvos sao passados ao JS via wp_localize_script para persistencia no painel.
* ADICIONADO: botao Testar cor no H1 no Diagnostico verifica pipeline completo.
* ADICIONADO: botao Remover teste H1 para limpar teste sem deixar CSS ativo.
* Updater GitHub preservado e validado.
= 1.4.7 =
* CORRIGIDO: seletores de override agora recebem prefixo body automaticamente para vencer especificidade do tema.
* CORRIGIDO: classes do editor visual sao removidas do seletor antes de salvar (public_safe_selector).
* CORRIGIDO: save AJAX agora verifica se override aparece no CSS compilado antes de retornar sucesso.
* CORRIGIDO: URL do CSS compilado agora usa set_url_scheme para evitar mixed content http/https.
* ADICIONADO: fallback inline duplicado em wp_footer alem de wp_head para contornar caching.
* ADICIONADO: armazenamento de diagnostico do ultimo salvamento visual.
* Painel Diagnostico de Aplicacao expandido com verificacao compiled+inline e ultimo save.
* Prioridade padrao do enqueue mantida em 99990.
= 1.4.6 =
* CORRIGIDO: propriedades individuais (padding-top, margin-left, border-width, border-style, etc.) eram silenciosamente rejeitadas pelo whitelist do scanner. Expandido para 30 propriedades.
* CORRIGIDO: handle_visual_save sempre retornava sucesso mesmo quando override era rejeitado. Agora retorna erro real com motivo.
* CORRIGIDO: seletor de grupo era fraco (ex: h3 puro). Agora inclui contexto do pai (.parent h3) para vencer especificidade do tema.
* ADICIONADO: fallback inline de emergencia — overrides sao impressos diretamente no wp_head (prioridade 99999) como rede de seguranca.
* ADICIONADO: normalizacao de valores CSS (virgula decimal para ponto, limpeza de ponto-e-virgula).
* ADICIONADO: painel Diagnostico de Aplicacao na aba Ferramentas com visibilidade completa do pipeline.
* Prioridade padrao do enqueue elevada para 99990.
* Updater GitHub preservado e validado.
= 1.4.5 =
* Editor visual do frontend refeito em modo didatico, com barra lateral estilo editor profissional: selecao por hover/click, breadcrumb visual, painel lateral por categorias e estado Saved/Unsaved.
* Corrigida a selecao pequena demais: o clique agora normaliza para o bloco real (titulo, paragrafo, botao, card, secao, menu, header, footer etc.), evitando alterar apenas uma letra/span interno.
* Controles visuais ampliados: fonte, cor, peso, tamanho, line-height, estilo, transformacao, decoracao, alinhamento, espacamentos, fundo, bordas, radius, medidas, sombra, filtros e transform.
* Preview em tempo real somente das propriedades alteradas; salvamento grava apenas controles modificados, evitando sobrescrever valores computados sem necessidade.
* Fundo transparente nao vira preto por padrao; valores transparentes ficam claros no painel e so sao salvos quando o usuario altera.
* Escopo de aplicacao: apenas elemento atual ou grupo similar, com detalhes tecnicos recolhidos.
* Visual alinhado a experiencia de edicao profissional, sem copiar codigo, assets, branding ou propriedade de terceiros.

= 1.4.3 =
* Novo editor visual: edite cores, fontes, espacamentos e bordas diretamente no frontend. Painel lateral flutuante com pre-visualizacao e salvamento via AJAX.
* Aba Visual adicionada ao painel admin com botao Abrir editor visual.
* Preview boxes adicionados aos presets (titulo, paragrafo, botao, swatches).
* Salvamento visual usa o motor de overrides existente do scanner.
* CSS compilado atualizado automaticamente apos cada alteracao visual.
* Somente administradores com manage_options e flag ?mlcssp_visual=1 acessam o editor visual.

= 1.4.2 =
* CORRIGIDO: admin JS procurava classe .mlcssp-admin-wrap mas o HTML usa .mlcssp-wrap, quebrando toda a navegacao por tabs. Corrigido o seletor e reescrito o admin.js completo.
* CORRIGIDO: tabs Cores, Tipografia, Botoes, Header, Rodape, Formularios, Layout, Seletores e todas as demais agora abrem corretamente ao clicar.
* ADICIONADO: botao "Add to editor" em cada propriedade detectada, inserindo snippet no editor avancado com CodeMirror.
* ADICIONADO: exposicao global das instancias CodeMirror para integracao com botoes de inserir snippet/detected.
* Updater GitHub validado: aponta para mlopesdesign/ml-css-pro, ignora drafts/pre-releases, injeta no transient de updates do WordPress.
* Cache-busting no enqueue frontend com filemtime.
* Todos os motores preservados: scanner, linter, snippets, historico, presets, licenca, ferramentas.

= 1.4.1 =
* Novo motor Scanner de CSS do frontend: varre a homepage (ou URL personalizada), le stylesheets linkados e inline, detecta seletores reais e propriedades editaveis.
* Paineis de Cores, Tipografia, Botoes, Header, Rodape, Formularios e Layout agora mostram dados reais da varredura, nao mais campos genericos com valores padrao.
* Override visual: altere um valor detectado e clique Aplicar para gerar CSS !important compilado automaticamente.
* Dashboard com controles de varredura (Scan Site CSS, Limpar cache, Recompilar, Abrir frontend) e status da varredura (arquivos, seletores, propriedades, data/hora).
* Tab Seletores agora inclui inventario completo de todos os seletores detectados na ultima varredura.
* Cache-busting no enqueue frontend: versao baseada em filemtime do CSS compilado.
* Overrides armazenados em opcao separada (mlcsspro_overrides) com autoload=no.
* Nonce e manage_options em todas as acoes de scan/override.
* Editor de codigo, snippets, historico, linter, updater e presets preservados da v1.4.0.

= 1.4.0 =
* Editor de codigo com CodeMirror nativo, validacao/lint, snippets, historico de revisoes com restauracao, assistente de seletores, updater auto-hospedado.

= 1.3.2 =
* Corrigidos seletores de botao para nao aplicar fundo em links comuns.
