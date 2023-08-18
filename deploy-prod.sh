#!/bin/bash

# Usage: ./deploy-prod.sh

release_name=$(date +"%Y%m%d_%H%M%S")
mkdir "releases/$release_name"

cd "releases/$release_name" || exit

git clone https://github.com/tuxonice/pixel-tracks.git .
../../bin/composer.phar install --no-dev
npm install
npx mix

./bin/console t:g

rm codeception.yml docker-compose.yml .env.dist .env.test .gitignore LICENSE .nvmrc phpcs.xml phpstan.neon README.md renovate.json
rm -rf .github tests docker docker-bin var

ln -s ../../shared/.env .env
ln -s ../../shared/var var

cd ../../

rm current
ln -s "releases/$release_name/public/" current
