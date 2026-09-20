<?php

/**
 * An installation failure whose message was written for the person running the
 * installer, and is therefore safe to display. Anything else is redacted.
 */
class InstallerError extends Exception
{
}
