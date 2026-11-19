// Dashboard: todos os números e gráficos vêm do banco, via api/dashboard/resumo.php

async function carregarDashboard() {
  try {
    const r = await chamarApi('../api/dashboard/resumo.php');
    document.getElementById('carregando').hidden = true;

    // Sem incidentes: mostra o estado vazio em vez de gráficos zerados
    if (r.total === 0) {
      document.getElementById('vazio').hidden = false;
      return;
    }

    // Mostra a área ANTES de desenhar, para o Chart.js medir o tamanho correto
    document.getElementById('conteudo-dashboard').hidden = false;

    document.getElementById('ind-total').textContent = r.total;
    document.getElementById('ind-abertos').textContent = r.abertos;
    document.getElementById('ind-resolvidos').textContent = r.resolvidos;
    document.getElementById('ind-tempo').textContent = formatarTempo(r.tempo_medio_horas);

    desenharGrafico('grafico-tipo', 'doughnut',
      r.por_tipo.map(l => l.nome),
      r.por_tipo.map(l => l.total),
      PALETA);

    desenharGrafico('grafico-prioridade', 'bar',
      r.por_prioridade.map(l => rotulo(l.prioridade)),
      r.por_prioridade.map(l => l.total),
      CORES_PRIORIDADE, false);

    desenharRecentes(r.recentes);
  } catch (erro) {
    document.getElementById('carregando').hidden = true;
    const caixa = document.getElementById('erro');
    limpar(caixa);
    caixa.appendChild(criarEstado('Não foi possível carregar o dashboard', erro.message, 'estado-erro'));
    caixa.hidden = false;
  }
}

function desenharRecentes(lista) {
  const ul = document.getElementById('lista-recentes');
  limpar(ul);

  lista.forEach(item => {
    const li = el('li');
    const link = el('a', null, item.titulo);
    link.href = 'incidente-detalhes.html?id=' + encodeURIComponent(item.id_incidente);
    li.appendChild(link);
    li.appendChild(badgePrioridade(item.prioridade));
    li.appendChild(badgeStatus(item.status));
    li.appendChild(el('span', 'data', formatarData(item.data_criacao)));
    ul.appendChild(li);
  });
}

carregarDashboard();
