<?php
/**
 * This file is part of the Cloudinary PHP package.
 *
 * (c) Cloudinary
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cloudinary\Transformation;

/**
 * Trait TransformationResizeTrait
 *
 * Here we add the most common 'aliases' for building transformation at the top level
 *
 * @api
 */
trait TransformationResizeTrait
{
    /**
     * Resize the asset using provided resize action
     *
     * @param BaseResizeAction $resize The resize action
     *
     */
    public function resize(BaseResizeAction $resize): static
    {
        return $this->addAction($resize);
    }

    /**
     * Change the size of the image exactly to the given width and height without necessarily retaining the original
     * aspect ratio: all original image parts are visible but might be stretched or shrunk.
     *
     * @param mixed $width       The required width of a transformed asset.
     * @param mixed $height      The required height of a transformed asset.
     * @param mixed $aspectRatio Resizes the asset to a new aspect ratio.
     *
     */
    public function scale($width = null, $height = null, $aspectRatio = null): static
    {
        return $this->resize(Scale::scale($width, $height, $aspectRatio));
    }

    /**
     * Extracts a region of the given width and height out of the original image.
     *
     * @param mixed                   $width   The required width of a transformed asset.
     * @param mixed                   $height  The required height of a transformed asset.
     * @param GravityQualifier|string $gravity Which part of the original image to include.
     * @param int|float|string|X      $x       Horizontal position for custom-coordinates based cropping
     * @param int|float|string|Y      $y       Vertical position for custom-coordinates based cropping
     *
     */
    public function crop($width = null, $height = null, $gravity = null, $x = null, $y = null): static
    {
        return $this->resize(Crop::crop($width, $height, $gravity, $x, $y));
    }

    /**
     * Creates an image with the exact given width and height without distorting the image.
     *
     * This option first scales up or down as much as needed to at least fill both of the given dimensions. If the
     * requested aspect ratio is different than the original, cropping will occur on the dimension that exceeds the
     * requested size after scaling.
     *
     * @param mixed                   $width   The required width of a transformed asset.
     * @param mixed                   $height  The required height of a transformed asset.
     * @param GravityQualifier|string $gravity Which part of the original image to include when the resulting image is
     *                                         smaller than the original or the proportions do not match.
     *
     */
    public function fill($width = null, $height = null, $gravity = null): static
    {
        return $this->resize(Fill::fill($width, $height, $gravity));
    }

    /**
     * Custom resize builder.
     *
     * @param string $name   Provide future (not supported in the current version) resize name
     * @param mixed  $width  The required width of a transformed asset.
     * @param mixed  $height The required height of a transformed asset.
     *
     */
    public function genericResize($name, $width = null, $height = null): static
    {
        return $this->resize(GenericResize::generic($name, $width, $height));
    }
}
