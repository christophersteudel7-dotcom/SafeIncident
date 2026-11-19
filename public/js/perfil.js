// Perfil: somente leitura (nome, e-mail e departamento do usuário logado).

async function carregarPerfil() {
  try {
    const p = await chamarApi('../api/perfil/perfil.php');
    document.getElementById('perfil-nome').textContent = p.nome;
    document.getElementById('perfil-avatar').textContent = p.nome.charAt(0).toUpperCase();
    document.getElementById('perfil-email').textContent = p.email;
    document.getElementById('perfil-departamento').textContent = p.departamento || 'Não informado';

    document.getElementById('carregando').hidden = true;
    document.getElementById('card-perfil').hidden = false;
  } catch (erro) {
    document.getElementById('carregando').hidden = true;
    const caixa = document.getElementById('erro');
    limpar(caixa);
    caixa.appendChild(criarEstado('Não foi possível carregar o perfil', erro.message, 'estado-erro'));
    caixa.hidden = false;
  }
}

carregarPerfil();
