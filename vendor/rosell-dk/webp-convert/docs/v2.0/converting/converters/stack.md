# Stack converter

The stack converter is a mechanism for trying all available converters until success. Well, the default is to try all converters, but this can be configured.

When calling `WebPConvert::convert($source, $destination, $options);`, you are actually invoking the stack converter.

## Passing options down to the individual converters

Any option that you pass to the Stack converter will be passed on to the individual converters. For example, setting options to the following will set the metadata option on all converters:

```php
$options = [
    'metadata' => 'all',
];
```

If you need the option to be different for a single converter there are several ways to do it:

#### 1. Prefixing

Options prefixed with a converter id are only effective for that converter, and overrides the non-prefixed option.

Ie, the following will set "metadata" to "all" for all converters, except *cwebp*, where "metadata" is set to "exif"

```php
$options = [
    'metadata' => 'all',
    'cwebp-metadata' => 'exif'
];
```

Prefixing is by the way a general feature in the way options are handled and thus not confined to the stack converter. (though it admittedly only finds its use in the context of a stack converter).


#### 2. Using the `converter-options` option
The *converter-options* option is convenient for setting a whole bunch of converter-specific options in one go.

Example:
```php
$options = [
    'converter-options' => [        
        'wpc' => [
            'crypt-api-key-in-transfer' => true
            'api-key' => 'my dog is white',
            'api-url' => 'https://example.com/wpc.php',
            'api-version' => 1,
        ],
    ],
]
```

#### 3. As part of the `converters` option
This option is explained further down this document.


## Modifying the stack

The default stack consists of the following converters:
- cwebp
- vips
- imagick
- gmagick
- imagemagick
- graphicsmagick
- wpc
- ewww
- gd

The order has carefully been chosen based on the capabilities of the converters. It is a rank, if you will.

Now, say that on your system, you only have *gd* working. With the default stack, this means that eight converters will be tested for operationality before getting to *gd* &ndash; each time a conversion is made. You might be tempted to optimizing the flow by putting *gd* on the top. *I would generally advise against this* for the following reasons:

1. It might be that one of the other (and better) converters starts working without you noticing. You will then miss out.
2. All converters have all been designed to exit very quickly when they are not operational. It only takes a few milliseconds for the library to detect that a converter is not operational - literally. For example, if no api key is provided for ewww, it will exit immediately.

However, there are valid reasons to modify the stack. For example, you may prefer *vips* over *cwebp*, or you may wish to remove a converter completely due to problems with that converter on your platform.

### Changing the order of the converters
To change the order, you can use the `preferred-converters` option. With this option you move selected converters to the top of the stack.

So, if you want the stack to start with *vips* and then *ewww*, but keep the rest of the order, you can set the following:

```php
$options[
    'preferred-converters' => ['vips', 'ewww'];
];
```

### Removing converters from the stack
To remove converters, you can use the `skip` option and prefixing. For example, to remove *cwebp* and *gd*:

```php
$options = [
    'ewww-skip' => true,
    'cwebp-skip' => true,
];
```

### Adding converters to the stack
If you are using a custom converter, you can add it to the stack like this:

```php
$options = [
    'extra-converters' => [
        '\\MyNameSpace\\WonderConverter'
    ],
];
```

It will be added to the bottom of the stack. To place it differently, use the `preferred-converters` option and set it to ie `'preferred-converters' => ['vips','\\MyNameSpace\\WonderConverter']`


Here is an example which adds an extra ewww converter. This way you can have a backup api-key in case the quota of the first has been exceeded.

```
$options = [
    'extra-converters' => [
        [
            'converter' => 'ewww',
            'options' => [
                'api-key' => 'provide-backup-key-here',
            ]
        ]
    ]
];
```
Note however that you will not be able to reorder that new ewww converter using `preferred-converters`, as there are now two converters with id=ewww, and that option has not been designed for that. Instead, you can add a sub-stack of ewww converters - see the "Stacking" section below.


### Setting the converter array explicitly
Using the `converters` option, you can set the converter array explicitly. What differentiates this from the `preferred-converters` option (besides that it completely redefines the converter ordering) is that it allows you to set both the converters *and* options for each converter in one go and that it allows a complex structure - such as a stack within a stack. Also, this structure can simplify things in some cases, such as when the options is generated by a GUI, as it is in WebP Express.

The array specifies the converters to try and their order. Each item can be:

- An id (ie "cwebp")
- A fully qualified class name (in case you have programmed your own custom converter)
- An array with two keys: "converter" and "options".

Example:

```php
$options = [
    'quality' => 71,
    'converters' => [
        'cwebp',        
        [
            'converter' => 'vips',
            'options' => [
                'quality' => 72                
            ]
        ],
        [
            'converter' => 'ewww',
            'options' => [
                'quality' => 73               
            ]
        ],
        'wpc',
        'imagemagick',
        '\\MyNameSpace\\WonderConverter'
    ],
];
```

### Stacking
Stack converters behave just like regular converters. They ARE in fact "regular", as they extend the same base class as all converters. This means that you can have a stack within a stack. You can for example utilize this for supplying a backup api key for the ewww converter. Like this:

```php
$options = [
    'ewww-skip' => true,   // skip the default ewww converter (we use stack of ewww converters instead)
    'extra-converters' => [
        [
            // stack of ewww converters
            'converter' => 'stack',
            'options' => [
                'ewww-skip' => false,       // do not skip ewww from here on
                'converters' => [
                    [
                        'converter' => 'ewww',
                        'options' => [
                            'api-key' => 'provide-preferred-key-here',
                        ]
                    ],
                    [
                        'converter' => 'ewww',
                        'options' => [
                            'api-key' => 'provide-backup-key-here',
                        ]
                    ]
                ],
            ]
        ]
    ],
    'preferred-converters' => ['cwebp', 'vips', 'stack'],    // set our stack of ewww converters third in queue
];
```
Note that we set `ewww-skip` in order to disable the *ewww* converter which is part of the defaults. As options are inherited, we have to reset this option again. These steps are not necessary when using the `converters` option.

Also note that the options for modifying the converters (`converters`, `extra-converters`, `converter-options`) does not get passed down.

Also note that if you want to add two stacks with `extra-converters`, the `preferred-converters` option will not work, as there are two converters called "stack". One workaround is to add those two stacks to their own stack, so you have three levels. Or you can of course simply use the `converters` option to get complete control.


### Shuffling

The stack can be configured to shuffling, meaning that the the order will be random. This can for example be used to balance load between several wpc instances in a sub stack.

Shuffling is enabled with the `shuffle` option.

Here is an example of balancing load between several *wpc* instances:

```php
$options = [
    'wpc-skip' => true,   // skip the default wpc converter (we use stack of wpc converters instead)
    'extra-converters' => [
        [
            // stack of wpc converters
            'converter' => 'stack',  
            'options' => [
                'wpc-skip' => false,    // do not skip wpc from here on
                'shuffle' => true,

                'converters' => [
                    [
                        'converter' => 'wpc',
                        'options' => [
                            'api-key' => 'my-dog',
                            'api-url' => 'my-wpc.com/wpc.php',
                            'api-version' => 1,
                            'crypt-api-key-in-transfer' => true,
                        ]
                    ],
                    [
                        'converter' => 'wpc',
                        'options' => [
                            'api-key' => 'my-other-dog',
                            'api-url' => 'my-other-wpc.com/wpc.php',
                            'api-version' => 1,
                            'crypt-api-key-in-transfer' => true,
                        ]
                    ]
                ],
            ]
        ]
    ],
    'preferred-converters' => ['cwebp', 'vips', 'stack'],    // set our stack of wpc converters third in queue
];
```
