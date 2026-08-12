const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  quit: () => ipcRenderer.invoke('app:quit'),
  savePdf: (opts) => ipcRenderer.invoke('pdf:save', opts),
  isElectron: true,
  platform: process.platform,
  version: process.versions.electron,
});
