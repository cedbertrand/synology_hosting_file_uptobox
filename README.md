# Synology hosting file Uptobox pour Download Station

Vous trouverez ici un fichier host permettant de gérer des téléchargements UPTOBOX via Download Station de SYNOLOGY.
Une fois le fichier host installé dans Download Station, il faut renseigner en login "token" et comme mot de passe votre TOKEN récupéré sur votre compte UPTOBOX. Cliquez sur "Vérifier", cela devrait vous indiquer sur votre compte est bien reconnu.

Testé sur DSM 7.2, Download Station 3.9.3, Compte uptobox premium.

V2.2

Il s'agit d'une mise à jour partant de la version 2.0 de Souli disponible ici:
https://www.nas-forum.com/forum/topic/44084-résolufichier-host-uptobox-host-file-uptobox/
Il y a eu à un moment donné une v2.1 (mais elle n'est plus disponible sur le forum), d'où cette v2.2.

Notes sur la v2.2:
- Intégration du domaine uptobox.eu en plus du domaine uptobox.com
- Ajustement de quelques regexp pour tenir compte notamment du nouveau domaine .eu
- Modification des URLs utilisées pour les appels API Uptobox
- Ajout de quelques prints en mode débug

Documentation Synology sur les fichiers host à intégrer dans Download Station:
https://global.download.synology.com/download/Document/Software/DeveloperGuide/Package/DownloadStation/All/enu/Developer_Guide_to_File_Hosting_Module.pdf

Vous n'avez besoin que du fichier .host, en effet, il contient lui même les 2 autres fichiers (INFO et PHP). Mais je trouve cela plus pratique d'avoir les fichiers sources à disposition directe dans github :).

Si vous modifiez les fichiers sources (INFO et UptoboxCom.php), pour reconstituer le fichier .host à intégrer dans Download Station du Nas SYNOLOGY il faut compresser les 2 fichiers INFO et UptoboxCom.php comme suit (c'est dans la doc):

```tar zcf UptoboxCom-2-2.host INFO UptoboxCom.php```
