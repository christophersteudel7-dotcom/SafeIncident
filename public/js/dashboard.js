async function verificarSessao() {
    const resposta = await fetch('../api/auth/sessao.php');
  
    if (!resposta.ok) {
      window.location.href = 'login.html'; // não está logado: volta para o login
      return;
    }
  
    const dados = await resposta.json();
    document.getElementById('boas-vindas').textContent =
      'Olá, ' + dados.nome + '! Você está autenticado.';
  }
  
  document.getElementById('btn-sair').addEventListener('click', async () => {
    await fetch('../api/auth/logout.php');
    window.location.href = 'login.html';
  });
  
  verificarSessao();