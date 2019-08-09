# toolkit-drush

## Diffy Commands

For more detailed documentation please refer to [docs/DiffyCommands.md](./docs/DiffyCommands.md)

| Command                  | Arguments                  | Options                         |
| ------------------------ | -------------------------- | ------------------------------- |
| `diffy:refresh-token`    |                            |                                 |
| `diffy:project-snapshot` | `project-id`               | `--environment=production`      |
| `diffy:project-compare`  | `project-id`               | `--environments=baseline-prod`  |
| `diffy:project-diff`     | `project-id`               | `--snapshot1=x` `--snapshot2=x` |
| `diffy:project-baseline` | `project-id` `snapshot-id` |                                 |