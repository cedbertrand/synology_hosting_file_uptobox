# Synology hosting file Uptobox

V2.2

Il s'agit d'une mise à jour partant de la version 2.1 de Souli disponible ici:
https://www.nas-forum.com/forum/topic/44084-résolufichier-host-uptobox-host-file-uptobox/

Notes sur la v2.2:
- Intégration du domaine uptobox.eu en plus du domaine uptobox.com
- Ajustement de quelques regexp pour tenir compte notamment du nouveau domaine .eu
- Modification des URLs utilisées pour les appels API Uptobox
- Ajout de quelques prints en mode débug

Documentation Synology:
https://global.download.synology.com/download/Document/Software/DeveloperGuide/Package/DownloadStation/All/enu/Developer_Guide_to_File_Hosting_Module.pdf

Vous n'avez besoin que du fichier .host, en effet, il contient lui même les 2 autres fichiers (INFO et PHP). Mais je trouve cela plus pratique d'avoir les fichiers sources à disposition directe dans github.

Pour constituer le fichier .host à intégrer dans Download Station du Nas SYNOLOGY il faut compresser les 2 fichiers INFO et UptoboxCom.php (c'est dans la doc):
```tar zcf UptoboxCom-2-2.host INFO UptoboxCom.php```
