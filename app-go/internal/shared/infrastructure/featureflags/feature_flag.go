// Package featureflags — config-based feature toggles
//
// Laravel: FeatureFlag.php (config/features.php)
// Spring: FeatureFlag.java (@ConfigurationProperties)
// Go: simple map wrapper
package featureflags

type FeatureFlag struct {
	flags map[string]bool
}

func New(flags map[string]bool) *FeatureFlag {
	return &FeatureFlag{flags: flags}
}

func (f *FeatureFlag) IsEnabled(name string) bool {
	return f.flags[name]
}
