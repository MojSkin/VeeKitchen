# Skills & Copying Rules — VeeKitchen

## Persian writing (آخرین قوانین کارفرما)
- Any Persian prose/UI text/documentation must follow the **persian-writing** skill (`.claude/skills/persian-writing`): natural register, ZWNJ (نیم‌فاصله), Persian digits where appropriate, «گیومه».
- Persian README/docs use the skill's orthography rules; keep code and identifiers in English.

## UI/UX
- Use the **ui-ux-pro-max** skill (`.claude/skills/ui-ux-pro-max`) when making design-system decisions (styles, palettes, typography).
- Use the **frontend-design** skill (`.claude/skills/frontend-design`) when building new UI: deliberate, non-templated visual choices.

## VeePanel reuse
- `D:/Projects/VeePanel` is an approved donor project. Its `resources/js/ui` kit (67 `Vee*` components, composables, tokens), module layouts, and helpers may be copied into VeeKitchen when needed.
- Adapt copies to VeeKitchen conventions (Toman/Money, RTL-first, glass theme) instead of vendoring blindly.
