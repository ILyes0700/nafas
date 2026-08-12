/**
 * Nafass — نَفَس — Electron main process
 * Charge l'app PHP servie par WAMP en local
 *   http://localhost/gabes-tatenafas/frontend/index.php
 *   (le chemin sur disque reste 'gabes-tatenafas' pour compatibilité WAMP)
 */

const { app, BrowserWindow, Menu, shell, ipcMain, dialog } = require('electron');
const path = require('path');
const fs = require('fs');

const APP_URL = process.env.GABES_URL || 'http://localhost/gabes-tatenafas-main/gabes-tatenafas/frontend/index.php';

let mainWindow;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 1100,
    minHeight: 720,
    title: 'Nafass — نَفَس · Gabès',
    backgroundColor: '#f8f7f2',
    icon: path.join(__dirname, '..', 'assets', process.platform === 'win32' ? 'icon.ico' : 'icon.png'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  mainWindow.loadURL(APP_URL).catch(() => {
    mainWindow.loadFile(path.join(__dirname, 'fallback.html'));
  });

  // ouvrir liens externes dans le navigateur
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (!url.startsWith('http://localhost')) {
      shell.openExternal(url);
      return { action: 'deny' };
    }
    return { action: 'allow' };
  });

  // Menu épuré
  const template = [
    {
      label: 'Application',
      submenu: [
        { label: 'Recharger', accelerator: 'CmdOrCtrl+R', click: () => mainWindow.reload() },
        { label: 'Plein écran', accelerator: 'F11', click: () => mainWindow.setFullScreen(!mainWindow.isFullScreen()) },
        { type: 'separator' },
        { label: 'Quitter', accelerator: 'CmdOrCtrl+Q', click: () => app.quit() },
      ],
    },
    {
      label: 'Affichage',
      submenu: [
        { role: 'zoomIn' }, { role: 'zoomOut' }, { role: 'resetZoom' }, { type: 'separator' }, { role: 'toggleDevTools' },
      ],
    },
    {
      label: 'Aide',
      submenu: [
        { label: 'À propos', click: () => mainWindow.webContents.executeJavaScript("window.location.hash='#/help'") },
      ],
    },
  ];
  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

ipcMain.handle('app:quit', () => app.quit());

/**
 * Sauvegarde un rapport HTML (servi par PHP) en PDF via Chromium printToPDF.
 *  - Charge l'URL dans une fenêtre cachée
 *  - Génère un PDF en mémoire (qualité parfaite, pas de dialog d'imprimante Windows)
 *  - Affiche un dialog « Enregistrer sous » → écrit le PDF sur disque
 *
 * Reçoit : { url: string, suggestedName?: string }
 * Retourne : { ok, path?, canceled?, error? }
 */
ipcMain.handle('pdf:save', async (_evt, { url, suggestedName } = {}) => {
  if (!url || !url.startsWith('http://localhost')) {
    return { ok: false, error: 'invalid-url' };
  }

  // 1) Demander d'abord l'emplacement de sauvegarde (si l'utilisateur annule, pas besoin de charger l'URL)
  const parent = BrowserWindow.getFocusedWindow() || mainWindow;
  const defaultName = suggestedName || ('rapport-' + Date.now() + '.pdf');
  const result = await dialog.showSaveDialog(parent, {
    title: 'Enregistrer le rapport en PDF',
    defaultPath: defaultName,
    filters: [{ name: 'PDF', extensions: ['pdf'] }],
  });
  if (result.canceled || !result.filePath) {
    return { ok: false, canceled: true };
  }

  // 2) Charger la page dans une fenêtre cachée
  const printWin = new BrowserWindow({
    show: false,
    webPreferences: { offscreen: false, contextIsolation: true, nodeIntegration: false },
  });

  try {
    await printWin.loadURL(url);
    // Petit délai pour laisser le CSS / fonts s'appliquer
    await new Promise(r => setTimeout(r, 400));

    const buffer = await printWin.webContents.printToPDF({
      pageSize: 'A4',
      printBackground: true,
      margins: { marginType: 'default' },
      landscape: false,
    });

    fs.writeFileSync(result.filePath, buffer);
    return { ok: true, path: result.filePath };
  } catch (err) {
    return { ok: false, error: String(err && err.message || err) };
  } finally {
    if (!printWin.isDestroyed()) printWin.close();
  }
});

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) createWindow();
});
