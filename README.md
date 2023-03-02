# VO Federation

This app enhances existing federation featues available in Nextcloud by joining users across multiple instances into so called virtual orgnaisations (VOs). Members are granted access to these VOs by authenticating their user accounts with a dedicated service called Community AAI, which implements the OIDC protocol.

## Usage

The latest release version of the app can be installed from the Nextcloud app store. Administrators need to configure new Community AAIs before they become available to users.

## Virtual organisation

A Virtual Organisation (VO) is a group of users which is managed within a Community AAI, as opposed to conventional groups which are managed in Nextcloud.

## Community AAI

AAI is short for “Authentication and Authorisation Infrastructure” and comprises the central component in gaining access to services like Nextcloud for several institutional users. In the context of this app the Community AAI is used predominantly as a directory service for group memberships.

Any OIDC provider can qualify as a Community AAI. However best compatibility with the app is guaranteed with AAIs implementing the [AARC Blueprint Architecture](https://aarc-project.eu/architecture/). 

Group memberships are synchronized between Nextcloud and the AAI in the background using the user's access token.

## Documentation

Please refer to the User guide and Administrator guide of the [documentation](https://nextcloud-vo-federation.readthedocs.io) for detailed explanations on how to use the app. Developers may find useful resources in the development section.

## Contribution

Feel free to [report bugs](https://github.com/nextcloud/vo_federation/issues) to the GitHub repository.
