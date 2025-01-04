#!/bin/bash

# Usage: ./deploy-prod.sh {tag or commit}
# Folder structure
# main-folder
#   |-- releases
#     |-- v0.1.1
#   |-- shared
#     |-- var
set -e

# Define the green color escape code
GREEN='\033[0;32m'

# Define the reset escape code to reset text color
NC='\033[0m' # No Color

MAIN_PATH=$(pwd)

if [ -z "$1" ]
then
    release_name=$(date +"%Y%m%d_%H%M%S")
else
    release_name=$1
fi

mkdir "releases/$release_name"

cd "releases/$release_name" || exit

echo -e "${GREEN}Cloning the repo ${NC}"
git clone https://github.com/tuxonice/pixel-tracks.git .
git checkout $release_name

echo -e "${GREEN}Remove not needed files ${NC}"
rm codeception.yml deploy-prod.sh docker-compose.yml .env.dist .env.test .gitignore LICENSE .nvmrc phpcs.xml phpstan.neon README.md renovate.json
rm -rf .github tests docker docker-bin var .git

echo -e "${GREEN}Setup permissions ${NC}"
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod +x bin/console

echo -e "${GREEN}Install composer dependencies ${NC}"
composer install --no-dev

echo -e "${GREEN}Install node dependencies ${NC}"
npm install
npx mix

rm -rf node_modules

ln -s $MAIN_PATH/shared/.env .env
ln -s $MAIN_PATH/shared/var var

echo -e "${GREEN}Transfer generate ${NC}"
./bin/console t:g

cd $MAIN_PATH || exit

echo -e "${GREEN}Link document root ${NC}"
rm current
ln -s "releases/$release_name/public/" current

echo -e "${GREEN}Done!${NC}"