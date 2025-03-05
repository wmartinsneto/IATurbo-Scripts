<?php
header("Content-Type: text/html; charset=UTF-8");

// Função para obter conteúdo remoto
function getRemoteContent($url) {
    $content = @file_get_contents($url);
    return $content !== false ? $content : "";
}

// Monta o código embed que será copiado
$embed_code = "";
// Parte de CSS
$embed_code .= "<!-- Incluir o CSS do chatbot via @import -->\n";
$embed_code .= "<style>\n@import url(\"https://iaturbo.com.br/wp-content/uploads/scripts/dify/client/client.css\");\n</style>\n\n";

// Parte do HTML: extrai apenas o conteúdo dentro de <body> do client.html
$html = getRemoteContent("https://iaturbo.com.br/wp-content/uploads/scripts/dify/client/client.html");
$body_content = "";
if ($html) {
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
        $body_content = trim($matches[1]);
    } else {
        $body_content = trim($html);
    }
}
$embed_code .= "<!-- Inserir a marcação HTML do chatbot -->\n" . $body_content . "\n\n";

// Parte do JavaScript
$embed_code .= "<!-- Inclusão do JavaScript do chatbot -->\n";
$embed_code .= "<script src=\"https://iaturbo.com.br/wp-content/uploads/scripts/dify/client/client.js\"></script>\n";

// Agora, exibe uma página com um textarea contendo o código embed e um botão para copiar
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Código Embedded do Chatbot</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    textarea { width: 100%; height: 300px; font-family: monospace; font-size: 14px; }
    button { padding: 10px 20px; font-size: 16px; margin-top: 10px; cursor: pointer; }
  </style>
</head>
<body>
  <h2>Código para Incorporação do Chatbot</h2>
  <p>Este é o código que você deve copiar e colar no Elementor (ou em qualquer outro local) para exibir o chatbot. 
  Ele carrega dinamicamente as referências necessárias para que atualizações na pasta <code>/client</code> sejam refletidas automaticamente.</p>
  <textarea id="embedCode" readonly><?php echo htmlspecialchars($embed_code); ?></textarea>
  <br>
  <button onclick="copyToClipboard()">Copiar</button>
  <script>
    function copyToClipboard(){
      var copyText = document.getElementById("embedCode");
      copyText.select();
      copyText.setSelectionRange(0, 99999); // Para dispositivos móveis
      document.execCommand("copy");
      alert("Código copiado para a área de transferência!");
    }
  </script>
</body>
</html>
