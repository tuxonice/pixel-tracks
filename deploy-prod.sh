#!/bin/bash

# Usage: ./deploy-prod.sh {tag or commit}
# Folder structure
# main-folder
#   |-- releases
#     |-- v0.1.1
#   |-- shared
#     |-- var

MAIN_PATH=$(pwd)

if [ -z "$1" ]
then
    release_name=$(date +"%Y%m%d_%H%M%S")
else
    release_name=$1
fi

mkdir "releases/$release_name"

cd "releases/$release_name" || exit

git clone https://github.com/tuxonice/pixel-tracks.git .
git checkout $release_name

$MAIN_PATH/bin/composer.phar install --no-dev
npm install
npx mix

rm codeception.yml deploy-prod.sh docker-compose.yml .env.dist .env.test .gitignore LICENSE .nvmrc phpcs.xml phpstan.neon README.md renovate.json
rm -rf .github tests docker docker-bin var .git

ln -s $MAIN_PATH/shared/.env .env
ln -s $MAIN_PATH/shared/var var

./bin/console t:g

cd $MAIN_PATH || exit

rm current
ln -s "releases/$release_name/public/" current
