PROGRESS_FILE=/tmp/clientSIP/compilation_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "*****************************************************************************************************"
echo "*                                    Installation PicoTTS                                           *"
echo "*****************************************************************************************************"
echo 10 > ${PROGRESS_FILE}
arch=`arch`;
if [[ $arch == "armv6l" || $arch == "armv7l" ]]
  then
    sudo apt-get install -y libsox-fmt-mp3 sox
    echo 30 > ${PROGRESS_FILE}
    sudo dpkg -i libttspico-data_1.0+git20130326-3_all.deb
    echo 50 > ${PROGRESS_FILE}
    sudo dpkg -i libttspico0_1.0+git20130326-3_armhf.deb
    echo 70 > ${PROGRESS_FILE}
    sudo dpkg -i libttspico-utils_1.0+git20130326-3_armhf.deb
  else
    sudo add-apt-repository non-free
    echo 30 > ${PROGRESS_FILE}
    sudo add-apt-repository contrib
    echo 50 > ${PROGRESS_FILE}
    sudo apt-get update
    echo 70 > ${PROGRESS_FILE}
    sudo apt-get install -y libsox-fmt-mp3 sox libttspico-utils
fi
echo 90 > ${PROGRESS_FILE}
echo "*****************************************************************************************************"
echo "*                              Ajout de www-data dans le groupe audio                               *"
echo "*****************************************************************************************************"
sudo usermod -a -G audio `whoami`
echo "*****************************************************************************************************"
echo "*                                          Fin de l'installation                                    *"
echo "*****************************************************************************************************"
echo 100 > ${PROGRESS_FILE}
rm ${PROGRESS_FILE}
