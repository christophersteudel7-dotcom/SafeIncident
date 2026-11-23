// Relatórios: o usuário escolhe os filtros, o PHP consulta o banco com eles
// Resumo e gráficos contam todos os filtrados. A tabela e o CSV trazem até 1.000 linhas.

const form = document.getElementById('form-filtros');
const erroFiltros = document.getElementById('erro-filtros');
const botaoCsv = document.getElementById('btn-csv');
const botaoGerar = document.getElementById('btn-gerar');
const instrucaoFiltros = document.getElementById('instrucao-filtros');
let ultimoResultado = null;
let filtrosDoResultado = '';
let numeroDaConsulta = 0;

function mostrarArea(nome) {
  document.getElementById('carregando').hidden = nome !== 'carregando';
  document.getElementById('erro').hidden = nome !== 'erro';
  document.getElementById('vazio').hidden = nome !== 'vazio';
  document.getElementById('resultado').hidden = nome !== 'resultado';
}

// ---------- Preenche os selects de tipo e sistema ----------

async function carregarOpcoes() {
  try {
    const tipos = await chamarApi('../api/incidentes/tipos.php');
    const selectTipo = document.getElementById('f-tipo');
    tipos.forEach(t => {
      const o = el('option', null, t.nome);
      o.value = t.id_tipo;
      selectTipo.appendChild(o);
    });

    const sistemas = await chamarApi('../api/incidentes/sistemas.php');
    const selectSistema = document.getElementById('f-sistema');
    sistemas.forEach(s => {
      const o = el('option', null, s);
      o.value = s;
      selectSistema.appendChild(o);
    });
  } catch (erro) {
    mostrarToast(erro.message, 'erro');
  }
}

// ---------- Geração do relatório ----------

function lerFiltros() {
  const params = new URLSearchParams();
  const campos = { id_tipo: 'f-tipo', sistema: 'f-sistema', status: 'f-status', prioridade: 'f-prioridade', data_inicio: 'f-inicio', data_fim: 'f-fim' };
  Object.keys(campos).forEach(chave => {
    const valor = document.getElementById(campos[chave]).value;
    if (valor) params.set(chave, valor);
  });
  return params;
}

function invalidarResultado() {
  // Uma resposta antiga perde a vez, mesmo que a nova consulta ainda não tenha começado.
  ++numeroDaConsulta;
  ultimoResultado = null;
  filtrosDoResultado = '';
  botaoCsv.disabled = true;
  botaoCsv.textContent = 'Exportar CSV (0 linhas)';
  botaoGerar.disabled = false;
  erroFiltros.hidden = true;
  instrucaoFiltros.hidden = false;
  mostrarArea('aguardando');
}

async function gerarRelatorio() {
  invalidarResultado();
  instrucaoFiltros.hidden = true;
  const minhaConsulta = numeroDaConsulta;
  const filtros = lerFiltros().toString();
  const inicio = document.getElementById('f-inicio').value;
  const fim = document.getElementById('f-fim').value;
  if (inicio && fim && inicio > fim) {
    erroFiltros.textContent = 'A data inicial não pode ser maior que a data final.';
    erroFiltros.hidden = false;
    return;
  }
  mostrarArea('carregando');
  botaoGerar.disabled = true;

  try {
    const r = await chamarApi('../api/relatorios/gerar.php?' + filtros);
    if (minhaConsulta !== numeroDaConsulta) return;
    if (filtros !== lerFiltros().toString()) {
      invalidarResultado();
      return;
    }

    if (r.resumo.total === 0) {
      const caixa = document.getElementById('vazio');
      limpar(caixa);
      caixa.appendChild(criarEstado('Nenhum incidente encontrado para os filtros selecionados', 'Altere os filtros e gere o relatório novamente.'));
      mostrarArea('vazio');
      return;
    }

    mostrarArea('resultado'); // mostra antes de desenhar, para o Chart.js medir o tamanho
    desenharResumo(r.resumo);
    desenharTabela(r);
    desenharGraficos(r);
    ultimoResultado = r;
    filtrosDoResultado = filtros;
    const linhas = r.dados.length;
    botaoCsv.textContent = 'Exportar CSV (' + linhas + (linhas === 1 ? ' linha)' : ' linhas)');
    botaoCsv.disabled = linhas === 0;
  } catch (erro) {
    if (minhaConsulta !== numeroDaConsulta) return;
    const caixa = document.getElementById('erro');
    limpar(caixa);
    caixa.appendChild(criarEstado('Não foi possível gerar o relatório', erro.message, 'estado-erro'));
    mostrarArea('erro');
  } finally {
    if (minhaConsulta === numeroDaConsulta) botaoGerar.disabled = false;
  }
}

function desenharResumo(resumo) {
  document.getElementById('res-total').textContent = resumo.total;
  document.getElementById('res-abertos').textContent = resumo.abertos;
  document.getElementById('res-resolvidos').textContent = resumo.resolvidos;
  document.getElementById('res-tempo').textContent = formatarTempo(resumo.tempo_medio_horas);
}

function desenharGraficos(r) {
  const verde = (lista) => lista.map(() => '#2FA97A');
  const nomes = (lista) => lista.map(l => l.nome);
  const totais = (lista) => lista.map(l => l.total);

  desenharGrafico('g-tipo', 'bar', nomes(r.por_tipo), totais(r.por_tipo), verde(r.por_tipo), true);
  desenharGrafico('g-status', 'doughnut', nomes(r.por_status), totais(r.por_status), CORES_STATUS);
  desenharGrafico('g-prioridade', 'bar', r.por_prioridade.map(l => rotulo(l.nome)), totais(r.por_prioridade), CORES_PRIORIDADE, false);
  desenharGrafico('g-sistema', 'bar', nomes(r.por_sistema), totais(r.por_sistema), verde(r.por_sistema), true);

  // Mês chega como AAAA-MM; na tela mostramos MM/AAAA
  const meses = r.por_mes.map(l => l.mes.substring(5, 7) + '/' + l.mes.substring(0, 4));
  desenharGrafico('g-mes', 'bar', meses, totais(r.por_mes), verde(r.por_mes), false);
}

function desenharTabela(r) {
  const corpo = document.getElementById('corpo-resultados');
  limpar(corpo);

  r.dados.forEach(i => {
    const tr = el('tr');

    const tdTitulo = el('td');
    const link = el('a', 'titulo-link', i.titulo);
    link.href = 'incidente-detalhes.html?id=' + encodeURIComponent(i.id_incidente);
    tdTitulo.appendChild(link);
    tr.appendChild(tdTitulo);

    tr.appendChild(el('td', null, i.tipo));
    tr.appendChild(el('td', null, i.sistema_afetado));

    const tdPrioridade = el('td');
    tdPrioridade.appendChild(badgePrioridade(i.prioridade));
    tr.appendChild(tdPrioridade);

    const tdStatus = el('td');
    tdStatus.appendChild(badgeStatus(i.status));
    tr.appendChild(tdStatus);

    tr.appendChild(el('td', null, formatarData(i.data_ocorrencia)));
    tr.appendChild(el('td', null, formatarData(i.data_resolucao)));
    corpo.appendChild(tr);
  });

  const aviso = document.getElementById('aviso-limite');
  if (r.resumo.total > r.dados.length) {
    aviso.textContent = 'A tabela e o CSV contêm os ' + r.dados.length + ' incidentes mais recentes de um total de ' + r.resumo.total + '. Refine os filtros para reduzir o resultado.';
    aviso.hidden = false;
  } else {
    aviso.hidden = true;
  }
}

// ---------- Exportar CSV (gerado no navegador, sem biblioteca) ----------

function celulaCsv(valor) {
  let texto = String(valor === null || valor === undefined ? '' : valor);
  // Se começar com = + - @ o Excel pode tratar como fórmula; o apóstrofo evita isso
  if (/^[=+\-@\t\r]/.test(texto)) texto = "'" + texto;
  return '"' + texto.replace(/"/g, '""') + '"';
}

function exportarCsv() {
  if (!ultimoResultado || botaoCsv.disabled) return;
  if (lerFiltros().toString() !== filtrosDoResultado) {
    invalidarResultado();
    return;
  }

  const cabecalho = ['ID', 'Título', 'Tipo de ataque', 'Sistema afetado', 'Prioridade', 'Status', 'Data da ocorrência', 'Data da resolução'];
  const linhas = ultimoResultado.dados.map(i => [
    i.id_incidente, i.titulo, i.tipo, i.sistema_afetado, rotulo(i.prioridade), i.status,
    formatarData(i.data_ocorrencia), i.data_resolucao ? formatarDataHora(i.data_resolucao) : ''
  ]);

  // Separador ";" e BOM no início: o Excel em português abre com acentos e colunas corretas
  const csv = [cabecalho].concat(linhas).map(l => l.map(celulaCsv).join(';')).join('\r\n');
  const arquivo = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });

  const link = document.createElement('a');
  link.href = URL.createObjectURL(arquivo);
  link.download = 'relatorio-safeincident-' + dataDeHoje() + '.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(link.href);
}

// ---------- Eventos ----------

form.addEventListener('input', invalidarResultado);
form.addEventListener('change', invalidarResultado);
form.addEventListener('submit', (e) => { e.preventDefault(); gerarRelatorio(); });

document.getElementById('btn-limpar').addEventListener('click', () => {
  form.reset();
  gerarRelatorio();
});

botaoCsv.addEventListener('click', exportarCsv);
document.getElementById('chartjs-cdn').addEventListener('load', () => {
  if (ultimoResultado) desenharGraficos(ultimoResultado);
});

// Ao abrir a tela, já gera o relatório com todos os dados
carregarOpcoes().then(() => {
  if (numeroDaConsulta === 0 && !voltandoAoLogin) gerarRelatorio();
});
