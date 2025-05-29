const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');
const chokidar = require('chokidar');
const WebSocket = require('ws');
const path = require('path');
const fs = require('fs');
const cors = require('cors');

// Конфигурация
const config = {
  port: 3000,
  phpServerUrl: 'http://localhost:80', // Адрес вашего PHP сервера (XAMPP/WAMP)
  watchPaths: ['**/*.php', '**/*.js', '**/*.css', '**/*.html'],
  ignorePaths: ['node_modules/**', '.git/**', 'server.js']
};

const app = express();
const server = require('http').createServer(app);
const wss = new WebSocket.Server({ server });

// CORS для разработки
app.use(cors());

// WebSocket для live reload
wss.on('connection', (ws) => {
  console.log('🔌 Client connected for live reload');
  
  ws.on('close', () => {
    console.log('🔌 Client disconnected');
  });
});

// Функция для отправки reload сигнала всем клиентам
function broadcastReload() {
  wss.clients.forEach((client) => {
    if (client.readyState === WebSocket.OPEN) {
      client.send(JSON.stringify({ type: 'reload' }));
    }
  });
}

// Настройка file watcher для live reload
const watcher = chokidar.watch(config.watchPaths, {
  ignored: config.ignorePaths,
  persistent: true,
  ignoreInitial: true
});

watcher.on('change', (filePath) => {
  console.log(`📝 File changed: ${filePath}`);
  broadcastReload();
});

// Прокси для PHP файлов
const phpProxy = createProxyMiddleware({
  target: config.phpServerUrl,
  changeOrigin: true,
  onError: (err, req, res) => {
    console.error('❌ PHP Proxy Error:', err.message);
    res.status(500).send(`
      <h1>PHP Server Error</h1>
      <p>Не удается подключиться к PHP серверу по адресу: <strong>${config.phpServerUrl}</strong></p>
      <p>Убедитесь, что запущен XAMPP/WAMP или другой PHP сервер</p>
      <hr>
      <p>Error: ${err.message}</p>
    `);
  },
  onProxyReq: (proxyReq, req, res) => {
    console.log(`🔄 Proxying: ${req.method} ${req.url}`);
  }
});

// Middleware для PHP файлов
app.use('*.php', phpProxy);

// Статические файлы (CSS, JS, изображения)
app.use(express.static('.', {
  index: false, // Не показывать index.html автоматически
  setHeaders: (res, path) => {
    // Отключаем кеширование для разработки
    res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    res.setHeader('Pragma', 'no-cache');
    res.setHeader('Expires', '0');
  }
}));

// Главная страница - перенаправляем на index.php
app.get('/', (req, res) => {
  res.redirect('/index.php');
});

// Инжектим live reload скрипт в HTML ответы
app.use((req, res, next) => {
  const originalSend = res.send;
  
  res.send = function(body) {
    if (typeof body === 'string' && body.includes('</body>')) {
      const liveReloadScript = `
        <script>
          (function() {
            const ws = new WebSocket('ws://localhost:${config.port}');
            ws.onmessage = function(event) {
              const data = JSON.parse(event.data);
              if (data.type === 'reload') {
                console.log('🔄 Live reload triggered');
                window.location.reload();
              }
            };
            ws.onopen = function() {
              console.log('🔌 Live reload connected');
            };
            ws.onerror = function(error) {
              console.log('❌ Live reload error:', error);
            };
          })();
        </script>
      `;
      body = body.replace('</body>', liveReloadScript + '</body>');
    }
    originalSend.call(this, body);
  };
  
  next();
});

// Запуск сервера
server.listen(config.port, () => {
  console.log('\n🚀 CRM Development Server запущен!');
  console.log(`📍 URL: http://localhost:${config.port}`);
  console.log(`🔄 PHP Proxy: ${config.phpServerUrl}`);
  console.log(`👀 Watching: ${config.watchPaths.join(', ')}`);
  console.log('\n💡 Советы:');
  console.log('   • Убедитесь, что запущен XAMPP/WAMP на порту 80');
  console.log('   • Изменения в файлах автоматически перезагрузят страницу');
  console.log('   • Для остановки нажмите Ctrl+C\n');
});

// Graceful shutdown
process.on('SIGINT', () => {
  console.log('\n🛑 Остановка сервера...');
  watcher.close();
  server.close(() => {
    console.log('✅ Сервер остановлен');
    process.exit(0);
  });
}); 