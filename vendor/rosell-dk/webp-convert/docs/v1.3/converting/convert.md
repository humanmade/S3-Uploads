# API: The convert() method

**WebPConvert::convert($source, $destination, $options, $logger)**

| Parameter        | Type    | Description                                                                                |
| ---------------- | ------- | ------------------------------------------------------------------------------------------ |
| `$source`        | String  | Absolute path to source image (only forward slashes allowed)                               |
| `$destination`   | String  | Absolute path to converted image (only forward slashes allowed)                            |
| `$options` (optional)      | Array   | Array of conversion (option) options                                             |
| `$logger` (optional)        | Baselogger   | Information about the conversion process will be passed to this object. Read more below                               |

Returns true if success or false if no converters are *operational*. If any converter seems to have its requirements met (are *operational*), but fails anyway, and no other converters in the stack could convert the image, an the exception from that converter is rethrown (either *ConverterFailedException* or *ConversionDeclinedException*). Exceptions are also thrown if something is wrong entirely (*InvalidFileExtensionException*, *TargetNotFoundException*, *ConverterNotFoundException*, *CreateDestinationFileException*, *CreateDestinationFolderException*, or any unanticipated exceptions thrown by the converters).

### Available options for all converters

Many options correspond to options of *cwebp*. These are documented [here](https://developers.google.com/speed/webp/docs/cwebp)


| Option            | Type    | Default                    | Description                                                          |
| ----------------- | ------- | -------------------------- | -------------------------------------------------------------------- |
| quality           | An integer between 0-100, or "auto" | "auto" | Lossy quality of converted image (JPEG only - PNGs are always losless).<br><br> If set to "auto", *WebPConvert* will try to determine the quality of the JPEG (this is only possible, if Imagick or GraphicsMagic is installed). If successfully determined, the quality of the webp will be set to the same as that of the JPEG. however not to more than specified in the new `max-quality` option. If quality cannot be determined, quality will be set to what is specified in the new `default-quality` option (however, if you use the *wpc* converter, it will also get a shot at detecting the quality) |
| max-quality           | An integer between 0-100 | 85 | See the `quality` option. Only relevant, when quality is set to "auto".
| default-quality           | An integer between 0-100 | 75 | See the `quality` option. Only relevant, when quality is set to "auto".
| metadata          | String  | 'none'                      | Valid values: all, none, exif, icc, xmp. Note: Only *cwebp* supports all values. *gd* will always remove all metadata. *ewww*, *imagick* and *gmagick* can either strip all, or keep all (they will keep all, unless metadata is set to *none*) |
| lossless          | Boolean | false ("auto" for pngs in 2.0)       | Encode the image without any loss. The option is ignored for PNG's (forced true). In 2.0, it can also be "auto", and it is not forced to anything - rather it deafaults to false for Jpegs and "auto" for PNGs |
| converters        | Array   | ['cwebp', 'gd', 'imagick']  | Specify conversion methods to use, and their order. Also optionally set converter options (see below) |
| converter-options | Array   | []                          | Set options of the individual converters (see below) |
| jpeg              | Array   | null                        | These options will be merged into the other options when source is jpeg |
| png               | Array   | null                        | These options will be merged into the other options when source is jpeg |
| skip (new in 2.0) | Boolean | false                       | If true, conversion will be skipped (ie for skipping png conversion for some converters) |
| skip-png (removed in 2.0) | Boolean | false               | If true, conversion will be skipped for png (ie for skipping png conversion for some converters) |

#### More on quality=auto
Unfortunately, *libwebp* does not provide a way to use the same quality for the converted image, as for source. This feature is implemented by *imagick* and *gmagick*. No matter which conversion method you choose, if you set *quality* to *auto*, our library will try to detect the quality of the source file using one of these libraries. If this isn't available, it will revert to the value set in the *default-quality* option (75 per default). *However*, with the *wpc* converter you have a second chance: If quality cannot be detected locally, it will send quality="auto" to *wpc*.

The bottom line is: If you do not have imagick or gmagick installed on your host (and have no way to install it), your best option quality-wise is to install *wpc* on a server that you do have access to, and connect to that. However,... read on:

**How much does it matter?**
The effect of not having quality detection is that jpeg images with medium quality (say 50) will be converted with higher quality (say 75). Converting a q=50 to a q=50 would typically result in a ~60% reduction. But converting it to q=75 will only result in a ~45% reduction. When converting low quality jpeg images, it gets worse. Converting q=30 to q=75 only achieves ~25% reduction.

I guess it is a rare case having jpeg images in low quality. Even having middle quality is rare, as there seems to have been a tendency to choose higher quality than actually needed for web. So, in many cases, the impact of not having quality detection is minor. If you set the *default-quality* a bit low, ie 65, you will further minimize the effect.

To determine if *webp-convert* is able to autodetect quality on your system, run a conversion with the *$logger* parameter set to `new EchoLogger()` (see api).

#### More on the `converter-options` option
You use this option to set options for the individual converters. Example:

```
'converter-options' => [
    'ewww' => [
        'key' => 'your-api-key-here'
    ],
    'wpc' => [
        'url' => 'https://example.com/wpc.php',
        'secret' => 'my dog is white'
    ]
]
```
Besides options that are special to a converter, you can also override general options. For example, you may generally want the `max-quality` to be 85, but for a single converter, you would like it to be 100 (sorry, it is hard to come up with a useful example).

#### More on the `converters` option
The *converters* option specifies the conversion methods to use and their order. But it can also be used as an alternative way of setting converter options. Usually, you probably want to use the *converter-options* for that, but there may be cases where it is more convenient to specify them here. Also, specifying here allows you to put the same converter method to the stack multiple times, with different options (this could for example be used to have an extra *ewww* converter as a fallback).

Example:
```
WebPConvert::convert($source, $destination, [
    'converters' => [
        'cwebp',    
        'imagick',
        [
            'converter' => 'ewww',
            'options' => [            
                'key' => 'your api key here',
            ],
        ],
    ];
)
```
In 2.0, it will be possible to use your own custom converter. Instead of the "converter id" (ie "ewww"), specify the full class name of your custom converter. Ie '\\MyProject\\BraveConverter'. The converter must extend `\WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter` and you must implement `doConvert()` and the define the extra options it takes (check out how it is done in the build-in converters).

### More on the `$logger` parameter
WebPConvert and the individual converters can provide information regarding the conversion process. Per default (when the parameter isn't provided), they write this to `\WebPConvert\Loggers\VoidLogger`, which does nothing with it.
In order to get this information echoed out, you can use `\WebPConvert\Loggers\EchoLogger` - like this:

```php
use WebPConvert\Loggers\EchoLogger;

WebPConvert::convert($source, $destination, $options, new EchoLogger());
```

In order to do something else with the information (perhaps write it to a log file?), you can extend `\WebPConvert\Loggers\BaseLogger`.

## Converters
In the most basic design, a converter consists of a static convert function which takes the same arguments as `WebPConvert::convert`. Its job is then to convert `$source` to WebP and save it at `$destination`, preferably taking the options specified in $options into account.

The converters may be called directly. But you probably don't want to do that, as it really doesn't hurt having other converters ready to take over, in case your preferred converter should fail.
