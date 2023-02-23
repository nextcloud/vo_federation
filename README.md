# VO Federation

## Try it 
To install it change into your Nextcloud's apps directory:

    cd nextcloud/apps

Then clone this repository into a folder named **vo_federation**:

    git clone https://github.com/nextcloud/vo_federation.git

Then install the dependencies using:

    make composer

## Frontend development

The app uses [Vue.js](https://vuejs.org/). To build the frontend code after doing changes to its source in `src/` requires to have Node and npm installed.

- 👩‍💻 Run `make dev-setup` to install the frontend dependencies
- 🏗 To build the Javascript whenever you make changes, run `make build-js`

To continuously run the build when editing source files you can make use of the `make watch-js` command.

## Running tests
You can use the provided Makefile to run all tests by using:

    make test

This will run the PHP unit and integration tests and if a package.json is present in the **js/** folder will execute **npm run test**