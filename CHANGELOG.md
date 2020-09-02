# Changelog

## 0.6.2 - 2020-09-02
### Fixed
- Fixed an error that would be thrown when copying a deactivated entry

## 0.6.1 - 2020-08-27
### Fixed
- Possible error when trying to copy global set ([#23](https://github.com/Goldinteractive/craft3-sitecopy/issues/23))

## 0.6.0 - 2020-08-13
### Added
- Ability to copy global sets

### Changed
- Automatic copy: Renamed current OR implementation to XOR and added new non-breaking "OR" check method.

### Fixed
- Deactivated fields now get copied to the target site too ([#21](https://github.com/Goldinteractive/craft3-sitecopy/issues/21))

## 0.5.3 - 2020-08-10
### Fixed
- Fixed an issue where unchanged neo blocks on the target site would be wiped ([#19](https://github.com/Goldinteractive/craft3-sitecopy/issues/19))

## 0.5.2 - 2020-04-28
### Fixed
- Fixed a bug where integer site ids would break the plugin functionality ([#13](https://github.com/Goldinteractive/craft3-sitecopy/issues/13))

## 0.5.1 - 2020-03-02
### Fixed
- Craft 3.4 compatibility

## 0.5.0 - 2020-02-05
### Added
- Possibility to choose what fields you want to copy

## 0.4.1 - 2019-12-06
### Fixed
- Fixed method parameter type "object" to ensure PHP 7.1 compatibility

## 0.4.0 - 2019-11-04
### Added
- Possibility to copy to multiple sites at once
- Compatibility for commerce products

### Changed
- Outsource element syncing to queue

