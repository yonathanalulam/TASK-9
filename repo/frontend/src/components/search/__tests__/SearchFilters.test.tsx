import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import SearchFilters from '../SearchFilters';
import type { SearchFilters as SearchFiltersType } from '@/hooks/useSearch';

describe('SearchFilters component', () => {
  const defaultProps = {
    filters: {} as SearchFiltersType,
    onFiltersChange: vi.fn(),
    sort: 'relevance' as const,
    onSortChange: vi.fn(),
  };

  it('renders store filter input', () => {
    render(<SearchFilters {...defaultProps} />);
    const storeInput = screen.getByTestId('search-filter-store');
    expect(storeInput).toBeDefined();
    expect(storeInput.getAttribute('placeholder')).toContain('store');
  });

  it('renders region filter input', () => {
    render(<SearchFilters {...defaultProps} />);
    const regionInput = screen.getByTestId('search-filter-region');
    expect(regionInput).toBeDefined();
    expect(regionInput.getAttribute('placeholder')).toContain('region');
  });

  it('includes store value in onFiltersChange when Apply is clicked', () => {
    const onFiltersChange = vi.fn();
    render(<SearchFilters {...defaultProps} onFiltersChange={onFiltersChange} />);

    const storeInput = screen.getByTestId('search-filter-store');
    fireEvent.change(storeInput, { target: { value: 'store-uuid-123' } });

    const applyButton = screen.getByText('Apply Filters');
    fireEvent.click(applyButton);

    expect(onFiltersChange).toHaveBeenCalledWith(
      expect.objectContaining({ store: 'store-uuid-123' }),
    );
  });

  it('includes region value in onFiltersChange when Apply is clicked', () => {
    const onFiltersChange = vi.fn();
    render(<SearchFilters {...defaultProps} onFiltersChange={onFiltersChange} />);

    const regionInput = screen.getByTestId('search-filter-region');
    fireEvent.change(regionInput, { target: { value: 'region-uuid-456' } });

    const applyButton = screen.getByText('Apply Filters');
    fireEvent.click(applyButton);

    expect(onFiltersChange).toHaveBeenCalledWith(
      expect.objectContaining({ region: 'region-uuid-456' }),
    );
  });

  it('clears store and region when Clear Filters is clicked', () => {
    const onFiltersChange = vi.fn();
    render(
      <SearchFilters
        {...defaultProps}
        filters={{ store: 'old-store', region: 'old-region' }}
        onFiltersChange={onFiltersChange}
      />,
    );

    const clearButton = screen.getByText('Clear Filters');
    fireEvent.click(clearButton);

    expect(onFiltersChange).toHaveBeenCalledWith({});
  });

  it('does not include empty store/region in filters', () => {
    const onFiltersChange = vi.fn();
    render(<SearchFilters {...defaultProps} onFiltersChange={onFiltersChange} />);

    // Click Apply without setting store or region
    const applyButton = screen.getByText('Apply Filters');
    fireEvent.click(applyButton);

    const call = onFiltersChange.mock.calls[0][0];
    expect(call.store).toBeUndefined();
    expect(call.region).toBeUndefined();
  });
});
