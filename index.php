<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Educational Chatbot & 3D Viewer</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app">
    <header class="header">
      <h1>EduBot</h1>
      <p class="tagline">Learn with the chatbot • View 3D models</p>
      <nav class="tabs">
        <button type="button" class="tab active" data-tab="chat">Chatbot</button>
        <button type="button" class="tab" data-tab="viewer">3D Viewer</button>
      </nav>
    </header>

    <main class="main">
      <!-- Chatbot panel -->
      <section id="panel-chat" class="panel active">
        <div class="chat-container">
          <div id="chat-messages" class="chat-messages">
            <div class="message bot">
              <span class="source-tag database">Database</span>
              <p>Hi! I'm your educational assistant. Ask me anything — I'll first look up answers in my knowledge base; if I don't find a match, I'll use AI. Try "What is photosynthesis?" or "How do I view a 3D model?"</p>
            </div>
          </div>
          <form id="chat-form" class="chat-form">
            <input type="text" id="chat-input" placeholder="Ask a question..." autocomplete="off">
            <button type="submit" id="chat-send">Send</button>
          </form>
        </div>
      </section>

      <!-- 3D Viewer panel -->
      <section id="panel-viewer" class="panel">
        <div class="viewer-toolbar">
          <label class="file-label">
            <input type="file" id="file-3d" accept=".glb,.gltf,.obj">
            Choose 3D file
          </label>
          <span class="file-hint">.glb, .gltf, or .obj (max 20 MB)</span>
          <button type="button" id="viewer-reset" class="btn-secondary">Reset view</button>
        </div>
        <div id="viewer-status" class="viewer-status hidden" aria-live="polite"></div>
        <div id="viewer-canvas-wrap" class="viewer-canvas-wrap">
          <canvas id="viewer-canvas"></canvas>
          <div id="viewer-placeholder" class="viewer-placeholder">
            <p>Upload a 3D model to view it here</p>
            <p class="small">Supported: .glb, .gltf, .obj</p>
          </div>
          <div id="viewer-loading" class="viewer-loading hidden">Loading.....</div>
        </div>
      </section>
    </main>

    <footer class="footer">
      <span>Database-first chatbot • Gemini AI fallback • Three.js 3D viewer</span>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/three@0.114.0/build/three.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.114.0/examples/js/loaders/GLTFLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.114.0/examples/js/loaders/OBJLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.114.0/examples/js/controls/OrbitControls.js"></script>
  <script src="assets/js/chat.js"></script>
  <script src="assets/js/viewer3d.js"></script>
</body>
</html>
