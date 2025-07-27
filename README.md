[project-name]: Monad
[project-url]: https://github.com/Tactics/Monad
[project-build]: https://github.com/Tactics/Monad/actions/workflows/quality_assurance.yaml/badge.svg
[project-tests]: https://github.com/Tactics/Monad/blob/main/badge-coverage.svg

# Foo
![Build][project-build]
![Tests][project-tests]
[![Software License][ico-license]](LICENSE.md)

Provide a detailed description of the project.

## Install

Make sure to add this to the *"repositories"* key in your ```composer.json```
since this is a private package hosted on our own Composer repository generator Satis.

```bash
"repositories": [
    {
        "type": "composer",
        "url": "https://satis.tactics.be"
    }
]
````

Then run the following command

``` bash
$ composer require tactics/monad
```

## Dependencies

When this package requires a new dependency make sure to install it through the docker container.
That way we can make sure the dependency is never out of sync with the php/composer version

## Testing

``` bash
$ composer test
```

## Static analysis

``` bash
$ composer phpstan
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email info at tactics dot be instead of using the issue tracker.

## Credits

Development of this library is sponsored by [Tactics]([link-owner]).

## License

The Lesser GPL version 3 or later. Please see [License File](LICENSE.md) for more information.

[link-owner]: https://github.com/Tactics
[link-contributors]: ../../contributors
[ico-license]: https://img.shields.io/badge/License-AGPLv3-green.svg?style=flat-square


